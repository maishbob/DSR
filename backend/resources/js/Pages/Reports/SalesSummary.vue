<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    rows: Array,
    station: Object,
    from: String,
    to: String,
});

const fromDate = ref(props.from);
const toDate = ref(props.to);

function filter() {
    router.get(route('reports.sales'), { from: fromDate.value, to: toDate.value });
}

const totals = computed(() => ({
    litres: props.rows.reduce((s, r) => s + Number(r.total_litres_sold), 0),
    revenue: props.rows.reduce((s, r) => s + Number(r.total_revenue), 0),
    cash: props.rows.reduce((s, r) => s + Number(r.total_cash_sales), 0),
    credit: props.rows.reduce((s, r) => s + Number(r.total_credit_sales), 0),
}));

function fmt(n, dec = 2) {
    return Number(n ?? 0).toLocaleString('en-KE', { minimumFractionDigits: dec, maximumFractionDigits: dec });
}
function fmtDate(d) {
    return d ? new Date(d).toLocaleDateString('en-KE') : '—';
}
</script>

<template>
    <Head title="Sales Summary" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Sales Summary</h1>
        </template>

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
        </div>

        <!-- Summary cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-orange-500">
                <p class="text-xs text-gray-400">Total Litres</p>
                <p class="text-xl font-bold text-gray-800">{{ fmt(totals.litres, 1) }} L</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500">
                <p class="text-xs text-gray-400">Total Revenue</p>
                <p class="text-xl font-bold text-gray-800">KES {{ fmt(totals.revenue) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
                <p class="text-xs text-gray-400">Cash</p>
                <p class="text-xl font-bold text-gray-800">KES {{ fmt(totals.cash) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-purple-500">
                <p class="text-xs text-gray-400">Credit</p>
                <p class="text-xl font-bold text-gray-800">KES {{ fmt(totals.credit) }}</p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">Date</th>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">Shift</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Litres</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Revenue</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Cash</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Credit</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Variance</th>
                        <th class="px-4 py-3 text-center text-gray-500 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in rows" :key="`${r.shift_date}-${r.shift_type}`" class="border-t border-gray-100">
                        <td class="px-4 py-2 text-gray-700">{{ fmtDate(r.shift_date) }}</td>
                        <td class="px-4 py-2 capitalize">{{ r.shift_type }}</td>
                        <td class="px-4 py-2 text-right">{{ fmt(r.total_litres_sold, 1) }}</td>
                        <td class="px-4 py-2 text-right font-medium">{{ fmt(r.total_revenue) }}</td>
                        <td class="px-4 py-2 text-right text-green-700">{{ fmt(r.total_cash_sales) }}</td>
                        <td class="px-4 py-2 text-right text-blue-700">{{ fmt(r.total_credit_sales) }}</td>
                        <td class="px-4 py-2 text-right font-semibold" :class="r.variance < 0 ? 'text-red-600' : 'text-green-600'">
                            {{ fmt(r.variance, 1) }}
                        </td>
                        <td class="px-4 py-2 text-center">
                            <span class="px-2 py-0.5 text-xs rounded-full"
                                :class="r.locked ? 'bg-purple-100 text-purple-700' : 'bg-yellow-100 text-yellow-700'">
                                {{ r.locked ? 'Locked' : 'Draft' }}
                            </span>
                        </td>
                    </tr>
                    <tr v-if="!rows?.length">
                        <td colspan="8" class="px-4 py-10 text-center text-gray-400">No data for selected period.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
