<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    station: Object,
    todayRevenue: Number,
    todayLitres: Number,
    todayVariance: Number,
    openShifts: Array,
    recentDeliveries: Array,
    revenueTrend: Array,
    topDebtors: Array,
});

function fmt(n, dec = 2) {
    return Number(n ?? 0).toLocaleString('en-KE', { minimumFractionDigits: dec, maximumFractionDigits: dec });
}
function fmtDate(d) {
    return d ? new Date(d).toLocaleDateString('en-KE') : '—';
}
</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">{{ station?.station_name }} — Dashboard</h1>
        </template>

        <!-- KPI Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-orange-500">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Today's Revenue</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">KES {{ fmt(todayRevenue) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-blue-500">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Litres Sold Today</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">{{ fmt(todayLitres, 1) }} L</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5"
                :class="(todayVariance ?? 0) < 0 ? 'border-l-4 border-red-500' : 'border-l-4 border-green-500'">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Stock Variance</p>
                <p class="text-2xl font-bold mt-1" :class="(todayVariance ?? 0) < 0 ? 'text-red-600' : 'text-green-600'">
                    {{ fmt(todayVariance, 1) }} L
                </p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-purple-500">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Open Shifts</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">{{ openShifts?.length ?? 0 }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Open Shifts -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-semibold text-gray-700">Today's Shifts</h2>
                    <Link :href="route('shifts.index')" class="text-sm text-orange-600 hover:underline">View all</Link>
                </div>
                <div v-if="openShifts?.length" class="space-y-3">
                    <div v-for="shift in openShifts" :key="shift.id"
                        class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div>
                            <span class="font-medium text-gray-800 capitalize">{{ shift.shift_type }} Shift</span>
                            <span class="ml-2 px-2 py-0.5 text-xs rounded-full"
                                :class="shift.status === 'open' ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600'">
                                {{ shift.status }}
                            </span>
                        </div>
                        <Link :href="route('shifts.show', shift.id)"
                            class="text-sm bg-orange-500 text-white px-3 py-1 rounded-lg hover:bg-orange-600">
                            Enter Data
                        </Link>
                    </div>
                </div>
                <div v-else class="text-center py-8">
                    <p class="text-gray-400 text-sm mb-3">No open shifts today.</p>
                    <Link :href="route('shifts.index')"
                        class="bg-orange-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-orange-600">
                        Open a Shift
                    </Link>
                </div>
            </div>

            <!-- Top Debtors -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-semibold text-gray-700">Top Debtors</h2>
                    <Link :href="route('credits.index')" class="text-sm text-orange-600 hover:underline">All</Link>
                </div>
                <div v-if="topDebtors?.length" class="space-y-3">
                    <div v-for="d in topDebtors" :key="d.id" class="flex justify-between items-center">
                        <span class="text-sm text-gray-700 truncate">{{ d.customer_name }}</span>
                        <span class="text-sm font-semibold text-red-600 ml-2 flex-shrink-0">{{ fmt(d.balance) }}</span>
                    </div>
                </div>
                <p v-else class="text-gray-400 text-sm text-center py-4">No outstanding balances.</p>
            </div>
        </div>

        <!-- Revenue Trend -->
        <div class="mt-6 bg-white rounded-xl shadow-sm p-5">
            <h2 class="font-semibold text-gray-700 mb-4">Last 7 Days Revenue</h2>
            <div v-if="revenueTrend?.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="pb-2 text-left text-gray-500 font-medium">Date</th>
                            <th class="pb-2 text-right text-gray-500 font-medium">Litres</th>
                            <th class="pb-2 text-right text-gray-500 font-medium">Revenue (KES)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in revenueTrend" :key="row.shift_date" class="border-b last:border-0">
                            <td class="py-2 text-gray-700">{{ fmtDate(row.shift_date) }}</td>
                            <td class="py-2 text-right text-gray-700">{{ fmt(row.litres, 1) }}</td>
                            <td class="py-2 text-right font-medium text-gray-800">{{ fmt(row.revenue) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="text-gray-400 text-sm text-center py-4">No data yet.</p>
        </div>

        <!-- Recent Deliveries -->
        <div class="mt-6 bg-white rounded-xl shadow-sm p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-gray-700">Recent Deliveries</h2>
                <Link :href="route('deliveries.index')" class="text-sm text-orange-600 hover:underline">View all</Link>
            </div>
            <div v-if="recentDeliveries?.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="pb-2 text-left text-gray-500 font-medium">Date</th>
                            <th class="pb-2 text-left text-gray-500 font-medium">Product</th>
                            <th class="pb-2 text-left text-gray-500 font-medium">Supplier</th>
                            <th class="pb-2 text-right text-gray-500 font-medium">Qty (L)</th>
                            <th class="pb-2 text-right text-gray-500 font-medium">Variance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="d in recentDeliveries" :key="d.id" class="border-b last:border-0">
                            <td class="py-2 text-gray-600">{{ fmtDate(d.delivery_date) }}</td>
                            <td class="py-2 text-gray-700">{{ d.product?.product_name }}</td>
                            <td class="py-2 text-gray-600">{{ d.supplier_name }}</td>
                            <td class="py-2 text-right">{{ fmt(d.delivery_quantity, 1) }}</td>
                            <td class="py-2 text-right"
                                :class="(d.delivery_variance ?? 0) < 0 ? 'text-red-600' : 'text-green-600'">
                                {{ d.delivery_variance != null ? fmt(d.delivery_variance, 1) : '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="text-gray-400 text-sm text-center py-4">No deliveries in the last 7 days.</p>
        </div>
    </AuthenticatedLayout>
</template>
