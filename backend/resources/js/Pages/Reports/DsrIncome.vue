<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { fmt2, fmtDate } from '@/composables/useFormatters';

const props = defineProps({
    rows: Array,
    station: Object,
    from: String,
    to: String,
});

const fromDate = ref(props.from);
const toDate   = ref(props.to);

function filter() {
    router.get(route('reports.dsr-income'), { from: fromDate.value, to: toDate.value });
}

const totals = computed(() => ({
    cash:   props.rows.reduce((s, r) => s + Number(r.total_cash_sales   ?? 0), 0),
    credit: props.rows.reduce((s, r) => s + Number(r.total_credit_sales ?? 0), 0),
    cards:  props.rows.reduce((s, r) => s + Number(r.total_card_sales   ?? 0), 0),
    pos:    props.rows.reduce((s, r) => s + Number(r.total_pos_sales    ?? 0), 0),
    mpesa:  props.rows.reduce((s, r) => s + Number(r.mpesa_collected    ?? 0), 0),
    total:  props.rows.reduce((s, r) => s + Number(r.total_revenue      ?? 0), 0),
}));

function pct(value, total) {
    if (!total) return '—';
    return (value / total * 100).toFixed(1) + '%';
}

function rowTotal(r) {
    return Number(r.total_revenue ?? 0);
}
</script>

<template>
    <Head title="DSR Income Report" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">DSR Income Report</h1>
        </template>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6 flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-xs text-gray-600 mb-1">From</label>
                <input type="date" v-model="fromDate" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">To</label>
                <input type="date" v-model="toDate" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <button @click="filter" class="bg-orange-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-orange-600">
                Apply
            </button>
        </div>

        <!-- Summary cards -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
                <p class="text-xs text-gray-400 uppercase tracking-wide">Cash</p>
                <p class="text-lg font-bold text-gray-800">{{ fmt2(totals.cash) }}</p>
                <p class="text-xs text-gray-400 mt-1">{{ pct(totals.cash, totals.total) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-purple-500">
                <p class="text-xs text-gray-400 uppercase tracking-wide">Account</p>
                <p class="text-lg font-bold text-gray-800">{{ fmt2(totals.credit) }}</p>
                <p class="text-xs text-gray-400 mt-1">{{ pct(totals.credit, totals.total) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-yellow-500">
                <p class="text-xs text-gray-400 uppercase tracking-wide">Cards</p>
                <p class="text-lg font-bold text-gray-800">{{ fmt2(totals.cards) }}</p>
                <p class="text-xs text-gray-400 mt-1">{{ pct(totals.cards, totals.total) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-red-500">
                <p class="text-xs text-gray-400 uppercase tracking-wide">POS</p>
                <p class="text-lg font-bold text-gray-800">{{ fmt2(totals.pos) }}</p>
                <p class="text-xs text-gray-400 mt-1">{{ pct(totals.pos, totals.total) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500">
                <p class="text-xs text-gray-400 uppercase tracking-wide">M-PESA</p>
                <p class="text-lg font-bold text-gray-800">{{ fmt2(totals.mpesa) }}</p>
                <p class="text-xs text-gray-400 mt-1">{{ pct(totals.mpesa, totals.total) }}</p>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-700 text-white text-xs uppercase">
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Shift</th>
                        <th class="px-4 py-3 text-right">DSR No.</th>
                        <th class="px-4 py-3 text-right">Cash</th>
                        <th class="px-4 py-3 text-right">%</th>
                        <th class="px-4 py-3 text-right">Account</th>
                        <th class="px-4 py-3 text-right">%</th>
                        <th class="px-4 py-3 text-right">Cards</th>
                        <th class="px-4 py-3 text-right">%</th>
                        <th class="px-4 py-3 text-right">POS</th>
                        <th class="px-4 py-3 text-right">%</th>
                        <th class="px-4 py-3 text-right">M-PESA</th>
                        <th class="px-4 py-3 text-right">%</th>
                        <th class="px-4 py-3 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="!rows.length">
                        <td colspan="14" class="px-4 py-8 text-center text-gray-400">No DSR records for this period.</td>
                    </tr>
                    <tr v-for="r in rows" :key="r.shift_date + r.shift_type" class="hover:bg-gray-50">
                        <td class="px-4 py-2 font-medium text-gray-800">{{ fmtDate(r.shift_date) }}</td>
                        <td class="px-4 py-2 text-gray-500 capitalize">{{ r.shift_type }}</td>
                        <td class="px-4 py-2 text-right font-mono text-gray-600">{{ r.serial_number ?? '—' }}</td>
                        <td class="px-4 py-2 text-right font-mono">{{ fmt2(r.total_cash_sales) }}</td>
                        <td class="px-4 py-2 text-right text-xs text-gray-400">{{ pct(Number(r.total_cash_sales), rowTotal(r)) }}</td>
                        <td class="px-4 py-2 text-right font-mono">{{ fmt2(r.total_credit_sales) }}</td>
                        <td class="px-4 py-2 text-right text-xs text-gray-400">{{ pct(Number(r.total_credit_sales), rowTotal(r)) }}</td>
                        <td class="px-4 py-2 text-right font-mono">{{ fmt2(r.total_card_sales) }}</td>
                        <td class="px-4 py-2 text-right text-xs text-gray-400">{{ pct(Number(r.total_card_sales), rowTotal(r)) }}</td>
                        <td class="px-4 py-2 text-right font-mono">{{ fmt2(r.total_pos_sales) }}</td>
                        <td class="px-4 py-2 text-right text-xs text-gray-400">{{ pct(Number(r.total_pos_sales), rowTotal(r)) }}</td>
                        <td class="px-4 py-2 text-right font-mono">{{ fmt2(r.mpesa_collected) }}</td>
                        <td class="px-4 py-2 text-right text-xs text-gray-400">{{ pct(Number(r.mpesa_collected), rowTotal(r)) }}</td>
                        <td class="px-4 py-2 text-right font-mono font-semibold text-gray-900">{{ fmt2(rowTotal(r)) }}</td>
                    </tr>
                </tbody>
                <tfoot v-if="rows.length" class="bg-gray-50 border-t-2 border-gray-300">
                    <tr class="text-sm font-semibold text-gray-700">
                        <td class="px-4 py-3" colspan="3">Totals ({{ rows.length }} DSR{{ rows.length !== 1 ? 's' : '' }})</td>
                        <td class="px-4 py-3 text-right font-mono">{{ fmt2(totals.cash) }}</td>
                        <td class="px-4 py-3 text-right text-xs text-gray-500">{{ pct(totals.cash, totals.total) }}</td>
                        <td class="px-4 py-3 text-right font-mono">{{ fmt2(totals.credit) }}</td>
                        <td class="px-4 py-3 text-right text-xs text-gray-500">{{ pct(totals.credit, totals.total) }}</td>
                        <td class="px-4 py-3 text-right font-mono">{{ fmt2(totals.cards) }}</td>
                        <td class="px-4 py-3 text-right text-xs text-gray-500">{{ pct(totals.cards, totals.total) }}</td>
                        <td class="px-4 py-3 text-right font-mono">{{ fmt2(totals.pos) }}</td>
                        <td class="px-4 py-3 text-right text-xs text-gray-500">{{ pct(totals.pos, totals.total) }}</td>
                        <td class="px-4 py-3 text-right font-mono">{{ fmt2(totals.mpesa) }}</td>
                        <td class="px-4 py-3 text-right text-xs text-gray-500">{{ pct(totals.mpesa, totals.total) }}</td>
                        <td class="px-4 py-3 text-right font-mono font-bold text-gray-900">{{ fmt2(totals.total) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
