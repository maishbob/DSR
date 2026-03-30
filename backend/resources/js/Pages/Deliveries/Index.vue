<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    deliveries: Object,
    station: Object,
});

const showForm = ref(false);

const form = useForm({
    product_id: '',
    tank_id: '',
    shift_id: '',
    delivery_date: new Date().toISOString().slice(0, 10),
    supplier_name: '',
    waybill_number: '',
    delivery_quantity: '',
    tank_dip_before: '',
    tank_dip_after: '',
    notes: '',
});

const filteredTanks = computed(() =>
    form.product_id
        ? props.station?.tanks?.filter(t => t.product_id == form.product_id)
        : props.station?.tanks ?? []
);

function submit() {
    form.post(route('deliveries.store'), {
        onSuccess: () => { showForm.value = false; form.reset(); },
    });
}

function fmt(n, dec = 2) {
    return Number(n ?? 0).toLocaleString('en-KE', { minimumFractionDigits: dec, maximumFractionDigits: dec });
}
function fmtDate(d) {
    return d ? new Date(d).toLocaleDateString('en-KE') : '—';
}
</script>

<template>
    <Head title="Deliveries" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Fuel Deliveries</h1>
        </template>

        <div class="flex justify-end mb-6">
            <button @click="showForm = !showForm"
                class="bg-orange-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-orange-600 font-medium">
                + Record Delivery
            </button>
        </div>

        <!-- Form -->
        <div v-if="showForm" class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h2 class="font-semibold text-gray-700 mb-4">Record Fuel Delivery</h2>
            <form @submit.prevent="submit" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Product *</label>
                    <select v-model="form.product_id" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">Select product</option>
                        <option v-for="p in station.products" :key="p.id" :value="p.id">{{ p.product_name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Tank *</label>
                    <select v-model="form.tank_id" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">Select tank</option>
                        <option v-for="t in filteredTanks" :key="t.id" :value="t.id">{{ t.tank_name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Delivery Date *</label>
                    <input type="date" v-model="form.delivery_date" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Supplier *</label>
                    <input type="text" v-model="form.supplier_name" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                        placeholder="e.g. Total Kenya" />
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Waybill / LPO No.</label>
                    <input type="text" v-model="form.waybill_number"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                        placeholder="Optional" />
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Delivery Quantity (L) *</label>
                    <input type="number" v-model="form.delivery_quantity" step="0.01" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                        placeholder="Litres as per invoice" />
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Tank Dip Before (L)</label>
                    <input type="number" v-model="form.tank_dip_before" step="0.01"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                        placeholder="Dip before delivery" />
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Tank Dip After (L)</label>
                    <input type="number" v-model="form.tank_dip_after" step="0.01"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                        placeholder="Dip after delivery" />
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Notes</label>
                    <input type="text" v-model="form.notes"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                        placeholder="Optional notes" />
                </div>
                <div class="sm:col-span-2 lg:col-span-3 flex gap-3">
                    <button type="submit" :disabled="form.processing"
                        class="bg-orange-500 text-white px-6 py-2 rounded-lg text-sm hover:bg-orange-600 disabled:opacity-60 font-medium">
                        Save Delivery
                    </button>
                    <button type="button" @click="showForm = false"
                        class="border border-gray-300 px-5 py-2 rounded-lg text-sm hover:bg-gray-50">
                        Cancel
                    </button>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">Date</th>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">Product</th>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">Tank</th>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">Supplier</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Qty (L)</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Dip Before</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Dip After</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Variance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="d in deliveries.data" :key="d.id" class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-700">{{ fmtDate(d.delivery_date) }}</td>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ d.product?.product_name }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ d.tank?.tank_name }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ d.supplier_name }}</td>
                        <td class="px-4 py-3 text-right font-medium">{{ fmt(d.delivery_quantity, 1) }}</td>
                        <td class="px-4 py-3 text-right text-gray-500">{{ d.tank_dip_before ? fmt(d.tank_dip_before, 1) : '—' }}</td>
                        <td class="px-4 py-3 text-right text-gray-500">{{ d.tank_dip_after ? fmt(d.tank_dip_after, 1) : '—' }}</td>
                        <td class="px-4 py-3 text-right font-semibold"
                            :class="d.delivery_variance == null ? 'text-gray-400' : d.delivery_variance < 0 ? 'text-red-600' : 'text-green-600'">
                            {{ d.delivery_variance != null ? fmt(d.delivery_variance, 1) : '—' }}
                        </td>
                    </tr>
                    <tr v-if="!deliveries.data?.length">
                        <td colspan="8" class="px-4 py-10 text-center text-gray-400">No deliveries recorded yet.</td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="deliveries.last_page > 1" class="px-4 py-3 border-t border-gray-100 flex justify-between items-center text-sm">
                <span class="text-gray-500">Page {{ deliveries.current_page }} of {{ deliveries.last_page }}</span>
                <div class="flex gap-2">
                    <Link v-if="deliveries.prev_page_url" :href="deliveries.prev_page_url"
                        class="px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50">Prev</Link>
                    <Link v-if="deliveries.next_page_url" :href="deliveries.next_page_url"
                        class="px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50">Next</Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
