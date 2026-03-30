<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { fmt, fmtDate } from '@/composables/useFormatters';

const props = defineProps({
    records: Object,
    station: Object,
});


</script>

<template>
    <Head title="DSR Records" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Daily Sales Records</h1>
        </template>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
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
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in records.data" :key="r.id" class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-700">{{ fmtDate(r.shift_date) }}</td>
                        <td class="px-4 py-3 capitalize text-gray-700">{{ r.shift_type }}</td>
                        <td class="px-4 py-3 text-right">{{ fmt(r.total_litres_sold, 1) }}</td>
                        <td class="px-4 py-3 text-right font-medium">{{ fmt(r.total_revenue) }}</td>
                        <td class="px-4 py-3 text-right text-green-700">{{ fmt(r.total_cash_sales) }}</td>
                        <td class="px-4 py-3 text-right text-blue-700">{{ fmt(r.total_credit_sales) }}</td>
                        <td class="px-4 py-3 text-right font-semibold"
                            :class="r.variance < 0 ? 'text-red-600' : 'text-green-600'">
                            {{ fmt(r.variance, 1) }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 text-xs rounded-full"
                                :class="r.locked ? 'bg-purple-100 text-purple-700' : 'bg-yellow-100 text-yellow-700'">
                                {{ r.locked ? 'Locked' : 'Draft' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="route('dsr.show', r.id)"
                                class="text-orange-600 hover:underline text-xs font-medium">
                                View →
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="!records.data?.length">
                        <td colspan="9" class="px-4 py-10 text-center text-gray-400">No DSR records found.</td>
                    </tr>
                </tbody>
            </table>

            <div v-if="records.last_page > 1" class="px-4 py-3 border-t flex justify-between items-center text-sm">
                <span class="text-gray-500">Page {{ records.current_page }} of {{ records.last_page }}</span>
                <div class="flex gap-2">
                    <Link v-if="records.prev_page_url" :href="records.prev_page_url"
                        class="px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50">Prev</Link>
                    <Link v-if="records.next_page_url" :href="records.next_page_url"
                        class="px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50">Next</Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
