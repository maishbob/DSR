<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    customers: Array,
    station: Object,
});

const showForm = ref(false);

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

function fmt(n, dec = 2) {
    return Number(n ?? 0).toLocaleString('en-KE', { minimumFractionDigits: dec, maximumFractionDigits: dec });
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
                        <label class="block text-xs text-gray-500 mb-1">Customer Name *</label>
                        <input type="text" v-model="form.customer_name" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                        <p v-if="form.errors.customer_name" class="text-red-500 text-xs mt-1">{{ form.errors.customer_name }}</p>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Contact Person</label>
                        <input type="text" v-model="form.contact"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Phone</label>
                        <input type="tel" v-model="form.phone"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="0700000000" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Email</label>
                        <input type="email" v-model="form.email"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">City / Town</label>
                        <input type="text" v-model="form.city"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Address</label>
                        <input type="text" v-model="form.address"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">KRA PIN</label>
                        <input type="text" v-model="form.pin"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="P123456789A" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">VAT Number</label>
                        <input type="text" v-model="form.vat_number"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Credit Limit (KES) *</label>
                        <input type="number" v-model="form.credit_limit" required min="0" step="0.01"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Opening Balance (KES)</label>
                        <input type="number" v-model="form.initial_opening_balance" step="0.01"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                            placeholder="0.00" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Discount Multiplier (0–1)</label>
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

        <!-- Customers table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">Customer</th>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">City</th>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">Phone</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Credit Limit</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Running Balance</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="c in customers" :key="c.id" class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ c.customer_name }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ c.city ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ c.phone ?? '—' }}</td>
                        <td class="px-4 py-3 text-right text-gray-600">{{ fmt(c.credit_limit) }}</td>
                        <td class="px-4 py-3 text-right font-semibold"
                            :class="c.balance > 0 ? 'text-red-600' : 'text-green-600'">
                            {{ fmt(c.balance) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <span class="px-2 py-0.5 text-xs rounded-full"
                                :class="c.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500'">
                                {{ c.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="route('credits.show', c.id)"
                                class="text-orange-600 hover:underline text-xs font-medium">
                                Statement →
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="!customers?.length">
                        <td colspan="7" class="px-4 py-10 text-center text-gray-400">No credit customers added yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
