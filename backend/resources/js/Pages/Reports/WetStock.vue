<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    rows: Array,
    station: Object,
    from: String,
    to: String,
});

const fromDate = ref(props.from);
const toDate = ref(props.to);

function filter() {
    router.get(route('reports.wet-stock'), { from: fromDate.value, to: toDate.value });
}

function fmt(n, dec = 2) {
    return Number(n ?? 0).toLocaleString('en-KE', { minimumFractionDigits: dec, maximumFractionDigits: dec });
}
function fmtDate(d) {
    return d ? new Date(d).toLocaleDateString('en-KE') : '—';
}
</script>

<template>
    <Head title="Wet Stock Report" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Wet Stock Report</h1>
        </template>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6 flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-xs text-gray-500 mb-1">From</label>
                <input type="date" v-model="fromDate" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">To</label>
                <input type="date" v-model="toDate" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <button @click="filter" class="bg-orange-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-orange-600">
                Apply
            </button>
            <button @click="window.print()" class="border border-gray-300 px-4 py-2 rounded-lg text-sm hover:bg-gray-50">
                Print
            </button>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
            <div class="p-5 border-b border-gray-100">
                <h2 class="font-semibold text-gray-700">{{ station.station_name }} — Wet Stock Reconciliation</h2>
                <p class="text-sm text-gray-500">{{ fmtDate(from) }} to {{ fmtDate(to) }}</p>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">Date</th>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">Shift</th>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">Product</th>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">Tank</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Opening</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">+ Deliveries</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">- Sold</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">= Expected</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Actual Dip</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Variance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, i) in rows" :key="i" class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="px-4 py-2 text-gray-700">{{ fmtDate(row.date) }}</td>
                        <td class="px-4 py-2 capitalize text-gray-600">{{ row.shift_type }}</td>
                        <td class="px-4 py-2 font-medium text-gray-800">{{ row.product }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ row.tank ?? '—' }}</td>
                        <td class="px-4 py-2 text-right">{{ fmt(row.opening_stock, 1) }}</td>
                        <td class="px-4 py-2 text-right text-blue-600">{{ fmt(row.deliveries, 1) }}</td>
                        <td class="px-4 py-2 text-right text-orange-600">{{ fmt(row.litres_sold, 1) }}</td>
                        <td class="px-4 py-2 text-right font-medium">{{ fmt(row.expected_stock, 1) }}</td>
                        <td class="px-4 py-2 text-right">{{ fmt(row.actual_stock, 1) }}</td>
                        <td class="px-4 py-2 text-right font-semibold"
                            :class="row.variance < 0 ? 'text-red-600' : row.variance > 0 ? 'text-green-600' : 'text-gray-400'">
                            {{ fmt(row.variance, 1) }}
                        </td>
                    </tr>
                    <tr v-if="!rows?.length">
                        <td colspan="10" class="px-4 py-10 text-center text-gray-400">No data for selected period.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
