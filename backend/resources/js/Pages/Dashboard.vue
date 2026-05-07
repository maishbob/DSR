<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { fmt, fmtDate } from '@/composables/useFormatters';

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


</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">{{ station?.station_name }} — Dashboard</h1>
        </template>

        <!-- KPI Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="kpi-card after:bg-orange-500 group hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">Today's Revenue</p>
                        <p class="text-2xl font-bold text-gray-800 mt-1.5">KES {{ fmt(todayRevenue) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center flex-shrink-0 group-hover:bg-orange-100 transition-colors">
                        <svg class="w-5 h-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="kpi-card after:bg-blue-500 group hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">Litres Sold Today</p>
                        <p class="text-2xl font-bold text-gray-800 mt-1.5">{{ fmt(todayLitres, 1) }} L</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0 group-hover:bg-blue-100 transition-colors">
                        <svg class="w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.572l-7.5-7.5-7.5 7.5M12 6.072v13.5" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="kpi-card group hover:shadow-md transition-shadow"
                :class="(todayVariance ?? 0) < 0 ? 'after:bg-red-500' : 'after:bg-green-500'">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">Stock Variance</p>
                        <p class="text-2xl font-bold mt-1.5" :class="(todayVariance ?? 0) < 0 ? 'text-red-600' : 'text-green-600'">
                            {{ fmt(todayVariance, 1) }} L
                        </p>
                    </div>
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 transition-colors"
                        :class="(todayVariance ?? 0) < 0 ? 'bg-red-50 group-hover:bg-red-100' : 'bg-green-50 group-hover:bg-green-100'">
                        <svg class="w-5 h-5" :class="(todayVariance ?? 0) < 0 ? 'text-red-500' : 'text-green-500'"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="kpi-card after:bg-purple-500 group hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">Open Shifts</p>
                        <p class="text-2xl font-bold text-gray-800 mt-1.5">{{ openShifts?.length ?? 0 }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-purple-50 flex items-center justify-center flex-shrink-0 group-hover:bg-purple-100 transition-colors">
                        <svg class="w-5 h-5 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Open Shifts -->
            <div class="lg:col-span-2 card p-5">
                <div class="section-heading">
                    <h2>Today's Shifts</h2>
                    <Link :href="route('shifts.index')" class="text-xs text-orange-600 hover:text-orange-700 font-medium hover:underline">View all &rarr;</Link>
                </div>
                <div v-if="openShifts?.length" class="space-y-2.5">
                    <div v-for="shift in openShifts" :key="shift.id"
                        class="flex items-center justify-between p-3.5 bg-gray-50/80 rounded-xl border border-gray-100 hover:border-orange-200 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                                :class="shift.status === 'open' ? 'bg-green-100' : 'bg-gray-100'">
                                <div class="w-2 h-2 rounded-full" :class="shift.status === 'open' ? 'bg-green-500' : 'bg-gray-400'"></div>
                            </div>
                            <div>
                                <span class="font-medium text-gray-800 capitalize text-sm">{{ shift.shift_type }} Shift</span>
                                <span class="ml-2 badge"
                                    :class="shift.status === 'open' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'">
                                    {{ shift.status }}
                                </span>
                            </div>
                        </div>
                        <Link :href="route('shifts.show', shift.id)"
                            class="text-sm bg-orange-500 text-white px-4 py-1.5 rounded-lg hover:bg-orange-600 font-medium transition-colors shadow-sm shadow-orange-500/20">
                            Enter Data
                        </Link>
                    </div>
                </div>
                <div v-else class="empty-state">
                    <div class="empty-icon">
                        <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <p class="mb-3">No open shifts today.</p>
                    <Link :href="route('shifts.index')"
                        class="inline-flex items-center bg-orange-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-orange-600 font-medium transition-colors shadow-sm shadow-orange-500/20">
                        Open a Shift
                    </Link>
                </div>
            </div>

            <!-- Top Debtors -->
            <div class="card p-5">
                <div class="section-heading">
                    <h2>Top Debtors</h2>
                    <Link :href="route('credits.index')" class="text-xs text-orange-600 hover:text-orange-700 font-medium hover:underline">All &rarr;</Link>
                </div>
                <div v-if="topDebtors?.length" class="space-y-3">
                    <div v-for="(d, i) in topDebtors" :key="d.id"
                        class="flex justify-between items-center py-2 border-b border-gray-50 last:border-0">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-5 h-5 rounded-full bg-gray-100 text-gray-500 text-xs flex items-center justify-center flex-shrink-0 font-medium">{{ i + 1 }}</span>
                            <span class="text-sm text-gray-700 truncate">{{ d.customer_name }}</span>
                        </div>
                        <span class="text-sm font-semibold text-red-600 ml-2 flex-shrink-0 tabular-nums">{{ fmt(d.balance) }}</span>
                    </div>
                </div>
                <div v-else class="empty-state">
                    <div class="empty-icon">
                        <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <p>No outstanding balances.</p>
                </div>
            </div>
        </div>

        <!-- Revenue Trend -->
        <div class="mt-6 card p-5">
            <div class="section-heading">
                <h2>Last 7 Days Revenue</h2>
            </div>
            <div v-if="revenueTrend?.length" class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="text-right">Litres</th>
                            <th class="text-right">Revenue (KES)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in revenueTrend" :key="row.shift_date">
                            <td class="text-gray-700">{{ fmtDate(row.shift_date) }}</td>
                            <td class="text-right text-gray-600 tabular-nums">{{ fmt(row.litres, 1) }}</td>
                            <td class="text-right font-semibold text-gray-800 tabular-nums">{{ fmt(row.revenue) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="empty-state">
                <div class="empty-icon">
                    <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75z" />
                    </svg>
                </div>
                <p>No data yet.</p>
            </div>
        </div>

        <!-- Recent Deliveries -->
        <div class="mt-6 card p-5">
            <div class="section-heading">
                <h2>Recent Deliveries</h2>
                <Link :href="route('deliveries.index')" class="text-xs text-orange-600 hover:text-orange-700 font-medium hover:underline">View all &rarr;</Link>
            </div>
            <div v-if="recentDeliveries?.length" class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Supplier</th>
                            <th class="text-right">Qty (L)</th>
                            <th class="text-right">Variance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="d in recentDeliveries" :key="d.id">
                            <td class="text-gray-600">{{ fmtDate(d.delivery_date) }}</td>
                            <td class="text-gray-700 font-medium">{{ d.product?.product_name }}</td>
                            <td class="text-gray-600">{{ d.supplier_name }}</td>
                            <td class="text-right tabular-nums">{{ fmt(d.delivery_quantity, 1) }}</td>
                            <td class="text-right font-medium tabular-nums"
                                :class="(d.delivery_variance ?? 0) < 0 ? 'text-red-600' : 'text-green-600'">
                                {{ d.delivery_variance != null ? fmt(d.delivery_variance, 1) : '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="empty-state">
                <div class="empty-icon">
                    <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                    </svg>
                </div>
                <p>No deliveries in the last 7 days.</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
