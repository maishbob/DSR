<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { fmt } from '@/composables/useFormatters';

const props = defineProps({
    customers: Array,
    station: Object,
});

const showForm = ref(false);

// ── Client-side search & pagination ──────────────────────────
const search      = ref('');
const perPage     = ref(20);
const currentPage = ref(1);

watch([search, perPage], () => { currentPage.value = 1; });

const filteredCustomers = computed(() => {
    const q = search.value.toLowerCase();
    if (!q) return props.customers ?? [];
    return (props.customers ?? []).filter(c =>
        c.customer_name.toLowerCase().includes(q) ||
        (c.city  ?? '').toLowerCase().includes(q)  ||
        (c.phone ?? '').toLowerCase().includes(q)
    );
});

const lastPage = computed(() => Math.max(1, Math.ceil(filteredCustomers.value.length / perPage.value)));
const from     = computed(() => filteredCustomers.value.length === 0 ? 0 : (currentPage.value - 1) * perPage.value + 1);
const to       = computed(() => Math.min(currentPage.value * perPage.value, filteredCustomers.value.length));

const pagedCustomers = computed(() =>
    filteredCustomers.value.slice((currentPage.value - 1) * perPage.value, currentPage.value * perPage.value)
);

const pageLinks = computed(() => {
    const total = lastPage.value;
    const cur   = currentPage.value;
    if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
    if (cur <= 4)         return [1, 2, 3, 4, 5, '…', total];
    if (cur >= total - 3) return [1, '…', total-4, total-3, total-2, total-1, total];
    return [1, '…', cur-1, cur, cur+1, '…', total];
});

const form = useForm({
    customer_name:            '',
    contact:                  '',
    phone:                    '',
    email:                    '',
    address:                  '',
    city:                     '',
    pin:                      '',
    vat_number:               '',
    is_withholding_vat_agent: false,
    credit_limit:             '',
    discount_multiplier:      '',
    initial_opening_balance:  '',
});

function submit() {
    form.post(route('credits.store'), {
        onSuccess: () => { showForm.value = false; form.reset(); },
    });
}


</script>

<template>
    <Head title="Credit Accounts" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Credit Accounts — Debtors Masterfile</h1>
        </template>

        <div class="flex justify-end mb-6">
            <button @click="showForm = !showForm"
                class="bg-orange-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-orange-600 font-medium">
                + Add Customer
            </button>
        </div>

        <!-- Add form -->
        <div v-if="showForm" class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h2 class="font-semibold text-gray-700 mb-4">New Credit Customer</h2>
            <form @submit.prevent="submit" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Customer Name *</label>
                        <input type="text" v-model="form.customer_name" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                        <p v-if="form.errors.customer_name" class="text-red-500 text-xs mt-1">{{ form.errors.customer_name }}</p>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Contact Person</label>
                        <input type="text" v-model="form.contact"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Phone</label>
                        <input type="tel" v-model="form.phone"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="0700000000" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Email</label>
                        <input type="email" v-model="form.email"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">City / Town</label>
                        <input type="text" v-model="form.city"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Address</label>
                        <input type="text" v-model="form.address"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">KRA PIN</label>
                        <input type="text" v-model="form.pin"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="P123456789A" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">VAT Number</label>
                        <input type="text" v-model="form.vat_number"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Credit Limit (KES) *</label>
                        <input type="number" v-model="form.credit_limit" required min="0" step="0.01"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Opening Balance (KES)</label>
                        <input type="number" v-model="form.initial_opening_balance" step="0.01"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                            placeholder="0.00" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Discount Multiplier (0–1)</label>
                        <input type="number" v-model="form.discount_multiplier" min="0" max="1" step="0.0001"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                            placeholder="e.g. 0.05 = 5%" />
                    </div>
                    <div class="flex items-center gap-2 pt-5">
                        <input type="checkbox" id="wht_new" v-model="form.is_withholding_vat_agent"
                            class="w-4 h-4 accent-orange-500" />
                        <label for="wht_new" class="text-sm text-gray-700">Withholding VAT Agent</label>
                    </div>
                </div>
                <div class="flex gap-2 pt-2">
                    <button type="submit" :disabled="form.processing"
                        class="bg-orange-500 text-white px-6 py-2 rounded-lg text-sm hover:bg-orange-600 disabled:opacity-60">
                        Save Customer
                    </button>
                    <button type="button" @click="showForm = false"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>

        <!-- DataTables-style container -->
        <div class="bg-white border border-gray-300 rounded text-sm">

            <!-- Top bar -->
            <div class="flex flex-wrap justify-between items-center gap-3 px-4 py-3 border-b border-gray-200 bg-gray-50">
                <div class="flex items-center gap-1.5 text-gray-600">
                    <span>Show</span>
                    <select v-model="perPage"
                        class="border border-gray-300 rounded px-1.5 py-0.5 text-sm bg-white focus:outline-none focus:ring-1 focus:ring-blue-400">
                        <option :value="10">10</option>
                        <option :value="20">20</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                    <span>entries</span>
                </div>
                <div class="flex items-center gap-1.5 text-gray-600">
                    <span>Search:</span>
                    <input v-model="search" type="text"
                        class="border border-gray-300 rounded px-2 py-0.5 text-sm w-44 focus:outline-none focus:ring-1 focus:ring-blue-400" />
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-gray-100 border-b-2 border-gray-300 text-left text-gray-700 font-semibold">
                            <th class="px-3 py-2.5 border-r border-gray-200">Customer</th>
                            <th class="px-3 py-2.5 border-r border-gray-200">City</th>
                            <th class="px-3 py-2.5 border-r border-gray-200">Phone</th>
                            <th class="px-3 py-2.5 text-right border-r border-gray-200">Credit Limit</th>
                            <th class="px-3 py-2.5 text-right border-r border-gray-200">Balance</th>
                            <th class="px-3 py-2.5 border-r border-gray-200">Status</th>
                            <th class="px-3 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(c, i) in pagedCustomers" :key="c.id"
                            class="border-b border-gray-200 hover:bg-blue-50"
                            :class="i % 2 === 1 ? 'bg-gray-50' : 'bg-white'">
                            <td class="px-3 py-2 font-medium text-gray-800 border-r border-gray-100">{{ c.customer_name }}</td>
                            <td class="px-3 py-2 text-gray-500 border-r border-gray-100">{{ c.city ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-600 border-r border-gray-100">{{ c.phone ?? '—' }}</td>
                            <td class="px-3 py-2 text-right text-gray-600 border-r border-gray-100">{{ fmt(c.credit_limit) }}</td>
                            <td class="px-3 py-2 text-right font-semibold border-r border-gray-100"
                                :class="c.balance > 0 ? 'text-red-600' : 'text-green-600'">
                                {{ fmt(c.balance) }}
                            </td>
                            <td class="px-3 py-2 border-r border-gray-100">
                                <span class="px-2 py-0.5 text-xs rounded"
                                    :class="c.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500'">
                                    {{ c.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <Link :href="route('credits.show', c.id)"
                                    class="text-orange-600 hover:underline text-xs font-medium">
                                    Statement →
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!pagedCustomers.length">
                            <td colspan="7" class="px-4 py-10 text-center text-gray-400 italic">
                                <template v-if="search">No customers match "{{ search }}".</template>
                                <template v-else>No credit customers added yet.</template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Bottom bar -->
            <div class="flex flex-wrap justify-between items-center gap-3 px-4 py-3 border-t border-gray-200 bg-gray-50">
                <span class="text-gray-600">
                    <template v-if="filteredCustomers.length > 0">
                        Showing {{ from }} to {{ to }} of {{ filteredCustomers.length }} entries
                        <span v-if="search" class="text-gray-400">(filtered from {{ customers.length }} total)</span>
                    </template>
                    <template v-else>No entries found</template>
                </span>

                <div class="flex gap-0.5">
                    <button :disabled="currentPage === 1"
                        @click="currentPage--"
                        class="px-3 py-1 border border-gray-300 rounded-l text-sm"
                        :class="currentPage === 1 ? 'text-gray-400 bg-gray-100 cursor-default' : 'text-gray-700 bg-white hover:bg-gray-100'">
                        Previous
                    </button>
                    <template v-for="p in pageLinks" :key="p">
                        <span v-if="p === '…'"
                            class="px-3 py-1 border-t border-b border-gray-300 text-gray-400 bg-white select-none">
                            …
                        </span>
                        <button v-else
                            @click="currentPage = p"
                            class="px-3 py-1 border-t border-b border-gray-300 text-sm"
                            :class="currentPage === p ? 'bg-blue-500 border-blue-500 text-white cursor-default' : 'bg-white text-gray-700 hover:bg-gray-100'">
                            {{ p }}
                        </button>
                    </template>
                    <button :disabled="currentPage === lastPage"
                        @click="currentPage++"
                        class="px-3 py-1 border border-gray-300 rounded-r text-sm"
                        :class="currentPage === lastPage ? 'text-gray-400 bg-gray-100 cursor-default' : 'text-gray-700 bg-white hover:bg-gray-100'">
                        Next
                    </button>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
