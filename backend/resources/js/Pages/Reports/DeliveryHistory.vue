<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { fmt, fmtDate } from '@/composables/useFormatters';

const props = defineProps({ rows: Array, station: Object, from: String, to: String });

const fromDate = ref(props.from);
const toDate = ref(props.to);

function filter() {
    router.get(route('reports.deliveries'), { from: fromDate.value, to: toDate.value });
}


</script>

<template>
    <Head title="Delivery History" />
    <AuthenticatedLayout>
        <template #header><h1 class="text-xl font-semibold text-gray-800">Delivery History</h1></template>

        <div class="bg-white rounded-xl shadow-sm p-4 mb-6 flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-xs text-gray-600 mb-1">From</label>
                <input type="date" v-model="fromDate" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">To</label>
                <input type="date" v-model="toDate" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <button @click="filter" class="bg-orange-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-orange-600">Apply</button>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">Date</th>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">Product</th>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">Tank</th>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">Supplier</th>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">Waybill</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Qty (L)</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Dip Before</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Dip After</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Variance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in rows" :key="r.id" class="border-t border-gray-100">
                        <td class="px-4 py-2 text-gray-700">{{ fmtDate(r.delivery_date) }}</td>
                        <td class="px-4 py-2 font-medium">{{ r.product?.product_name }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ r.tank?.tank_name }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ r.supplier_name }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ r.waybill_number ?? '—' }}</td>
                        <td class="px-4 py-2 text-right font-medium">{{ fmt(r.delivery_quantity, 1) }}</td>
                        <td class="px-4 py-2 text-right">{{ r.tank_dip_before ? fmt(r.tank_dip_before, 1) : '—' }}</td>
                        <td class="px-4 py-2 text-right">{{ r.tank_dip_after ? fmt(r.tank_dip_after, 1) : '—' }}</td>
                        <td class="px-4 py-2 text-right font-semibold"
                            :class="r.delivery_variance == null ? 'text-gray-400' : r.delivery_variance < 0 ? 'text-red-600' : 'text-green-600'">
                            {{ r.delivery_variance != null ? fmt(r.delivery_variance, 1) : '—' }}
                        </td>
                    </tr>
                    <tr v-if="!rows?.length">
                        <td colspan="9" class="px-4 py-10 text-center text-gray-400">No deliveries in selected period.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
