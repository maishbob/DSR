<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ rows: Array, station: Object, from: String, to: String });

const fromDate = ref(props.from);
const toDate = ref(props.to);

function filter() {
    router.get(route('reports.variance'), { from: fromDate.value, to: toDate.value });
}

function fmt(n, dec = 1) {
    return Number(n ?? 0).toLocaleString('en-KE', { minimumFractionDigits: dec, maximumFractionDigits: dec });
}
function fmtDate(d) {
    return d ? new Date(d).toLocaleDateString('en-KE') : '—';
}
</script>

<template>
    <Head title="Variance Report" />
    <AuthenticatedLayout>
        <template #header><h1 class="text-xl font-semibold text-gray-800">Variance Report</h1></template>

        <div class="bg-white rounded-xl shadow-sm p-4 mb-6 flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-xs text-gray-500 mb-1">From</label>
                <input type="date" v-model="fromDate" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">To</label>
                <input type="date" v-model="toDate" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <button @click="filter" class="bg-orange-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-orange-600">Apply</button>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-semibold text-gray-700">Shifts with Stock Variance</h2>
                <p class="text-sm text-gray-500">{{ rows.length }} records found</p>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">Date</th>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">Shift</th>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">Product</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Expected</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Actual</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Variance (L)</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Severity</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(r, i) in rows" :key="i" class="border-t border-gray-100">
                        <td class="px-4 py-2 text-gray-700">{{ fmtDate(r.date) }}</td>
                        <td class="px-4 py-2 capitalize">{{ r.shift_type }}</td>
                        <td class="px-4 py-2 font-medium">{{ r.product }}</td>
                        <td class="px-4 py-2 text-right">{{ fmt(r.expected_stock) }}</td>
                        <td class="px-4 py-2 text-right">{{ fmt(r.actual_stock) }}</td>
                        <td class="px-4 py-2 text-right font-bold text-lg"
                            :class="r.variance < 0 ? 'text-red-600' : 'text-green-600'">
                            {{ fmt(r.variance) }}
                        </td>
                        <td class="px-4 py-2 text-right">
                            <span class="px-2 py-0.5 text-xs rounded-full font-medium"
                                :class="Math.abs(r.variance) > 100 ? 'bg-red-100 text-red-700' :
                                        Math.abs(r.variance) > 50 ? 'bg-orange-100 text-orange-700' :
                                        'bg-yellow-100 text-yellow-700'">
                                {{ Math.abs(r.variance) > 100 ? 'High' : Math.abs(r.variance) > 50 ? 'Medium' : 'Low' }}
                            </span>
                        </td>
                    </tr>
                    <tr v-if="!rows?.length">
                        <td colspan="7" class="px-4 py-10 text-center text-gray-400">No variances detected in selected period.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
