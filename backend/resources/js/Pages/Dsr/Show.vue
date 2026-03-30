<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { fmt, fmtDate } from '@/composables/useFormatters';

const props = defineProps({
    dsr: Object,
    varianceLabels: Object,
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const isManager = computed(() => ['owner', 'manager'].includes(user.value.role));

// ── Confirm modal ─────────────────────────────────────────────
const confirmModal = ref({ show: false, title: '', message: '', variant: 'danger', onConfirm: () => {} });
function openConfirm({ title, message, variant = 'danger', onConfirm }) {
    confirmModal.value = { show: true, title, message, variant, onConfirm };
}
function closeConfirm() { confirmModal.value.show = false; }
function handleConfirm() { confirmModal.value.onConfirm(); closeConfirm(); }

const adjForm = useForm({
    adjustment_type: '',
    reason: '',
    original_value: '',
    corrected_value: '',
});

const approveForm = useForm({ override_reason: '' });
const showAdjForm = ref(false);
const showOverrideInput = ref(false);

const varianceStatus = computed(() => props.dsr.variance_status ?? 'ok');
const isCritical     = computed(() => varianceStatus.value === 'critical');

const varianceStatusClass = computed(() => ({
    ok:       'bg-green-100 text-green-700',
    warning:  'bg-yellow-100 text-yellow-800',
    critical: 'bg-red-100 text-red-700',
}[varianceStatus.value] ?? 'bg-gray-100 text-gray-700'));

function submitAdj() {
    adjForm.post(route('dsr.adjustments.store', props.dsr.id), {
        onSuccess: () => { showAdjForm.value = false; adjForm.reset(); },
    });
}

function approve() {
    if (isCritical.value && !approveForm.override_reason.trim()) {
        showOverrideInput.value = true;
        return;
    }
    openConfirm({
        title: 'Approve DSR',
        message: 'Approve and lock this DSR? This cannot be undone.',
        variant: 'warning',
        onConfirm: () => approveForm.post(route('dsr.approve', props.dsr.id)),
    });
}

function print() {
    window.print();
}

</script>

<template>
    <Head :title="`DSR — ${dsr.shift_date}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center gap-3">
                    <Link :href="route('dsr.index')" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </Link>
                    <h1 class="text-lg font-semibold text-gray-800">
                        DSR — {{ fmtDate(dsr.shift_date) }} ({{ dsr.shift_type }})
                    </h1>
                    <span class="px-2 py-0.5 text-xs rounded-full font-medium"
                        :class="dsr.locked ? 'bg-purple-100 text-purple-700' : 'bg-yellow-100 text-yellow-700'">
                        {{ dsr.locked ? 'Locked' : 'Draft' }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <!-- Variance status badge -->
                    <span v-if="dsr.variance_status" class="px-2 py-0.5 rounded text-xs font-semibold" :class="varianceStatusClass">
                        {{ dsr.variance_status?.toUpperCase() }}
                    </span>
                    <button @click="print"
                        class="border border-gray-300 text-gray-700 px-3 py-1.5 rounded-lg text-sm hover:bg-gray-50">
                        Print
                    </button>
                    <button v-if="!dsr.locked && isManager" @click="approve"
                        :class="isCritical ? 'bg-red-600 hover:bg-red-700' : 'bg-purple-600 hover:bg-purple-700'"
                        class="text-white px-4 py-1.5 rounded-lg text-sm font-medium">
                        {{ isCritical ? 'Override & Approve' : 'Approve & Lock' }}
                    </button>
                    <button v-if="dsr.locked" @click="showAdjForm = !showAdjForm"
                        class="border border-orange-500 text-orange-600 px-4 py-1.5 rounded-lg text-sm hover:bg-orange-50">
                        Add Adjustment
                    </button>
                </div>
            </div>
        </template>

        <!-- Critical variance override input -->
        <div v-if="showOverrideInput && !dsr.locked"
            class="mb-4 rounded-lg border border-red-300 bg-red-50 p-4">
            <p class="text-sm font-semibold text-red-800 mb-2">
                This DSR has a CRITICAL variance. An override reason is required to approve it.
            </p>
            <p class="text-xs text-red-600 mb-3">{{ varianceLabels?.critical }}</p>
            <textarea v-model="approveForm.override_reason" rows="3" placeholder="State the reason for approving despite the critical variance…"
                class="w-full border border-red-300 rounded px-3 py-2 text-sm focus:ring-red-400 focus:border-red-400 mb-2"></textarea>
            <p v-if="approveForm.errors.dsr" class="text-xs text-red-700 mb-2">{{ approveForm.errors.dsr }}</p>
            <div class="flex gap-2">
                <button @click="approve"
                    class="bg-red-600 text-white px-4 py-1.5 rounded text-sm font-medium hover:bg-red-700">
                    Confirm Override & Approve
                </button>
                <button @click="showOverrideInput = false"
                    class="border border-gray-300 text-gray-700 px-4 py-1.5 rounded text-sm hover:bg-gray-50">
                    Cancel
                </button>
            </div>
        </div>

        <!-- Override record (when already approved with override) -->
        <div v-if="dsr.locked && dsr.override_reason"
            class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
            <span class="font-semibold">Approved with override:</span> {{ dsr.override_reason }}
        </div>

        <!-- DSR Header Summary -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6 print:shadow-none">
            <div class="text-center mb-4 print:block">
                <h2 class="text-lg font-bold text-gray-800">{{ dsr.station?.station_name }}</h2>
                <p class="text-gray-500 text-sm">Daily Sales Record — {{ fmtDate(dsr.shift_date) }} ({{ dsr.shift_type }} shift)</p>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="text-center p-4 bg-orange-50 rounded-xl">
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Total Litres Sold</p>
                    <p class="text-2xl font-bold text-orange-600 mt-1">{{ fmt(dsr.total_litres_sold, 1) }} L</p>
                </div>
                <div class="text-center p-4 bg-green-50 rounded-xl">
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Total Revenue</p>
                    <p class="text-2xl font-bold text-green-700 mt-1">KES {{ fmt(dsr.total_revenue) }}</p>
                </div>
                <div class="text-center p-4 bg-blue-50 rounded-xl">
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Cash Sales</p>
                    <p class="text-2xl font-bold text-blue-700 mt-1">KES {{ fmt(dsr.total_cash_sales) }}</p>
                </div>
                <div class="text-center p-4" :class="dsr.variance < 0 ? 'bg-red-50' : 'bg-green-50'">
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Stock Variance</p>
                    <p class="text-2xl font-bold mt-1" :class="dsr.variance < 0 ? 'text-red-600' : 'text-green-700'">
                        {{ fmt(dsr.variance, 1) }} L
                    </p>
                </div>
            </div>
        </div>

        <!-- Per-product breakdown -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6 print:shadow-none">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-semibold text-gray-700">Product Breakdown</h2>
                <p v-if="dsr.approved_by" class="text-xs text-gray-400">
                    Approved by {{ dsr.approved_by?.name }} on {{ fmtDate(dsr.approved_at) }}
                </p>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-gray-500 font-medium">Product</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Opening Stock</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Deliveries</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Litres Sold</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Expected Stock</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Actual (Dip)</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Variance</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Price/L</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Revenue</th>
                        <th class="px-4 py-3 text-right text-gray-500 font-medium">Credit</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in dsr.line_items" :key="item.id" class="border-t border-gray-100">
                        <td class="px-4 py-3 font-semibold text-gray-800">{{ item.product?.product_name }}</td>
                        <td class="px-4 py-3 text-right">{{ fmt(item.opening_stock, 1) }}</td>
                        <td class="px-4 py-3 text-right text-blue-600">{{ fmt(item.deliveries, 1) }}</td>
                        <td class="px-4 py-3 text-right font-medium text-orange-600">{{ fmt(item.litres_sold, 1) }}</td>
                        <td class="px-4 py-3 text-right">{{ fmt(item.expected_stock, 1) }}</td>
                        <td class="px-4 py-3 text-right">{{ fmt(item.actual_stock, 1) }}</td>
                        <td class="px-4 py-3 text-right font-semibold"
                            :class="item.variance < 0 ? 'text-red-600' : 'text-green-600'">
                            {{ fmt(item.variance, 1) }}
                        </td>
                        <td class="px-4 py-3 text-right text-gray-500">{{ fmt(item.price_per_litre, 4) }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-green-700">{{ fmt(item.revenue) }}</td>
                        <td class="px-4 py-3 text-right text-gray-600">{{ fmt(item.credit_sales_value) }}</td>
                    </tr>
                    <!-- Totals row -->
                    <tr class="border-t-2 border-gray-300 bg-gray-50 font-semibold">
                        <td class="px-4 py-3 text-gray-700">TOTAL</td>
                        <td class="px-4 py-3 text-right">—</td>
                        <td class="px-4 py-3 text-right text-blue-600">{{ fmt(dsr.total_deliveries, 1) }}</td>
                        <td class="px-4 py-3 text-right text-orange-600">{{ fmt(dsr.total_litres_sold, 1) }}</td>
                        <td class="px-4 py-3 text-right">{{ fmt(dsr.expected_stock, 1) }}</td>
                        <td class="px-4 py-3 text-right">{{ fmt(dsr.actual_stock, 1) }}</td>
                        <td class="px-4 py-3 text-right" :class="dsr.variance < 0 ? 'text-red-600' : 'text-green-600'">
                            {{ fmt(dsr.variance, 1) }}
                        </td>
                        <td class="px-4 py-3 text-right">—</td>
                        <td class="px-4 py-3 text-right text-green-700">{{ fmt(dsr.total_revenue) }}</td>
                        <td class="px-4 py-3 text-right">{{ fmt(dsr.total_credit_sales) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Adjustments (if locked) -->
        <div v-if="dsr.locked">
            <!-- Add adjustment form -->
            <div v-if="showAdjForm" class="bg-white rounded-xl shadow-sm p-5 mb-6">
                <h2 class="font-semibold text-gray-700 mb-4">Record Adjustment</h2>
                <form @submit.prevent="submitAdj" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Adjustment Type</label>
                        <input type="text" v-model="adjForm.adjustment_type" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                            placeholder="e.g. meter_reading, stock_correction" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Reason *</label>
                        <input type="text" v-model="adjForm.reason" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Original Value</label>
                        <input type="number" v-model="adjForm.original_value" step="any"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Corrected Value</label>
                        <input type="number" v-model="adjForm.corrected_value" step="any"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div class="sm:col-span-2 flex gap-2">
                        <button type="submit" :disabled="adjForm.processing"
                            class="bg-orange-500 text-white px-5 py-2 rounded-lg text-sm hover:bg-orange-600 disabled:opacity-60">
                            Save Adjustment
                        </button>
                        <button type="button" @click="showAdjForm = false"
                            class="border border-gray-300 px-4 py-2 rounded-lg text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>

            <!-- Adjustments list -->
            <div v-if="dsr.adjustments?.length" class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-5 border-b border-gray-100">
                    <h2 class="font-semibold text-gray-700">Post-Lock Adjustments</h2>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-gray-500 font-medium">Type</th>
                            <th class="px-4 py-3 text-left text-gray-500 font-medium">Reason</th>
                            <th class="px-4 py-3 text-right text-gray-500 font-medium">Original</th>
                            <th class="px-4 py-3 text-right text-gray-500 font-medium">Corrected</th>
                            <th class="px-4 py-3 text-left text-gray-500 font-medium">By</th>
                            <th class="px-4 py-3 text-left text-gray-500 font-medium">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="adj in dsr.adjustments" :key="adj.id" class="border-t border-gray-100">
                            <td class="px-4 py-2 text-gray-700">{{ adj.adjustment_type }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ adj.reason }}</td>
                            <td class="px-4 py-2 text-right">{{ adj.original_value ?? '—' }}</td>
                            <td class="px-4 py-2 text-right font-medium">{{ adj.corrected_value ?? '—' }}</td>
                            <td class="px-4 py-2">{{ adj.created_by?.name ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ fmtDate(adj.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>

    <ConfirmModal
        :show="confirmModal.show"
        :title="confirmModal.title"
        :message="confirmModal.message"
        :variant="confirmModal.variant"
        @confirm="handleConfirm"
        @cancel="closeConfirm" />
</template>

<style>
@media print {
    nav, aside, button, .print\:hidden { display: none !important; }
    body { background: white; }
}
</style>
