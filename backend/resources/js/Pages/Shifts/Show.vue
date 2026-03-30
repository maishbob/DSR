<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { fmt, fmt2 } from '@/composables/useFormatters';

const props = defineProps({
    shift: Object,
    station: Object,
    cashReconciliation: Object,
});

// ── Tab management ────────────────────────────────────────────────────────────
const activeTab = ref('pumps');
const tabs = [
    { key: 'pumps',    label: 'Pump Readings' },
    { key: 'oils',     label: 'Oils' },
    { key: 'shortage', label: 'Shortage Calculation' },
    { key: 'clients',  label: 'Client Sales' },
    { key: 'cards',    label: 'Cards' },
    { key: 'pos',      label: 'POS' },
    { key: 'expenses', label: 'Expenses' },
    { key: 'summary',  label: 'Sales Summary' },
];

const isLocked = computed(() => props.shift.status === 'locked');

// ── Confirm Modal ────────────────────────────────────────────────────────────
const confirmModal = ref({ show: false, title: '', message: '', variant: 'danger', onConfirm: () => {} });

function openConfirm({ title, message, variant = 'danger', onConfirm }) {
    confirmModal.value = { show: true, title, message, variant, onConfirm };
}
function closeConfirm() {
    confirmModal.value.show = false;
}
function handleConfirm() {
    confirmModal.value.onConfirm();
    closeConfirm();
}

// ── Tab counts ────────────────────────────────────────────────────────────────
const tabCounts = computed(() => ({
    pumps:    props.shift.meter_readings?.length ?? 0,
    oils:     props.shift.oil_sales?.length ?? 0,
    shortage: 0,
    clients:  props.shift.credit_sales?.length ?? 0,
    cards:    props.shift.card_payments?.length ?? 0,
    pos:      props.shift.pos_transactions?.length ?? 0,
    expenses: props.shift.expenses?.length ?? 0,
    summary:  0,
}));

// ── Pump Readings ─────────────────────────────────────────────────────────────
const meterForm = useForm({
    nozzle_id:          '',
    closing_mechanical: '',
    closing_electrical: '',
    closing_shs:        '',
});

// Selected nozzle's pre-seeded reading (for showing opening values read-only)
const selectedReading = ref(null);

// Pre-fill closing fields from existing reading when nozzle selected
function onNozzleSelect() {
    const existing = getMeterByNozzle(meterForm.nozzle_id);
    const nozzle   = props.station?.pump_nozzles?.find(n => n.id == meterForm.nozzle_id);

    // Always prefer nozzle's current last_mech/last_elec for display — these are
    // authoritative (updated on shift close). The pre-seeded opening may be 0 if
    // the nozzle readings were set after the shift was opened.
    const openMech = (nozzle?.last_mech != null && Number(nozzle.last_mech) > 0)
        ? nozzle.last_mech : existing?.opening_mechanical ?? 0;
    const openElec = (nozzle?.last_elec != null && Number(nozzle.last_elec) > 0)
        ? nozzle.last_elec : existing?.opening_electrical ?? 0;
    const openShs  = (nozzle?.last_shs  != null && Number(nozzle.last_shs)  > 0)
        ? nozzle.last_shs  : existing?.opening_shs ?? null;

    if (existing) {
        selectedReading.value = {
            ...existing,
            opening_mechanical: openMech,
            opening_electrical: openElec,
            opening_shs:        openShs,
        };
        meterForm.closing_mechanical = existing.closing_mechanical ?? '';
        meterForm.closing_electrical = existing.closing_electrical ?? '';
        meterForm.closing_shs        = existing.closing_shs ?? '';
    } else {
        selectedReading.value = nozzle ? {
            id:                 null,
            opening_mechanical: openMech,
            opening_electrical: openElec,
            opening_shs:        openShs,
            closing_mechanical: null,
            closing_electrical: null,
            closing_shs:        null,
        } : null;
        meterForm.closing_mechanical = '';
        meterForm.closing_electrical = '';
        meterForm.closing_shs        = '';
    }
}

function submitMeter() {
    meterForm.post(route('meter-readings.store', props.shift.id), {
        onSuccess: () => { meterForm.reset(); selectedReading.value = null; },
    });
}

function clearMeterReading() {
    if (!selectedReading.value?.id) return;
    openConfirm({
        title: 'Clear Reading',
        message: 'Clear closing readings for this nozzle?',
        onConfirm: () => {
            router.delete(route('meter-readings.destroy', selectedReading.value.id), {
                preserveScroll: true,
                onSuccess: () => { meterForm.reset(); selectedReading.value = null; },
            });
        },
    });
}

function getMeterByNozzle(nozzleId) {
    return props.shift.meter_readings?.find(m => m.nozzle_id == nozzleId);
}

const nozzlesByProduct = computed(() => {
    const groups = {};
    (props.station?.pump_nozzles ?? []).forEach(n => {
        const name = n.product?.product_name ?? 'Unknown';
        if (!groups[name]) groups[name] = [];
        groups[name].push(n);
    });
    return groups;
});

const pumpSalesTotal = computed(() => {
    return (props.shift.meter_readings ?? []).reduce((sum, r) => sum + Number(r.litres_sold ?? 0), 0);
});

const pumpRevenueTotal = computed(() => {
    // Revenue = litres × price, grouped by product via nozzle
    return (props.shift.meter_readings ?? []).reduce((sum, r) => {
        const price = getCurrentPrice(r.nozzle?.product_id);
        return sum + Number(r.litres_sold ?? 0) * price;
    }, 0);
});

function getCurrentPrice(productId) {
    const product = props.station?.products?.find(p => p.id == productId);
    return Number(product?.price_histories?.[0]?.price_per_litre ?? 0);
}

// ── Oils / Shop Products ──────────────────────────────────────────────────────
const oilForm = useForm({
    shop_product_id: '',
    opening_stock:   '',
    quantity:        '',
    unit_price:      '',
});

function onShopProductSelect() {
    const sp = props.station?.shop_products?.find(p => p.id == oilForm.shop_product_id);
    if (sp) {
        oilForm.unit_price = sp.current_price;
        // Pre-fill opening stock from previous shift's closing stock if available
        const existingSale = props.shift.oil_sales?.find(s => s.shop_product_id == oilForm.shop_product_id);
        if (existingSale) oilForm.opening_stock = existingSale.opening_stock ?? '';
    }
}

function submitOilSale() {
    oilForm.post(route('oil-sales.store', props.shift.id), {
        onSuccess: () => oilForm.reset(),
    });
}

function deleteOilSale(id) {
    openConfirm({
        title: 'Remove Oil Sale',
        message: 'Remove this oil sale?',
        onConfirm: () => {
            router.delete(route('oil-sales.destroy', id));
        },
    });
}

const totalOilSales = computed(() =>
    (props.shift.oil_sales ?? []).reduce((sum, s) => sum + Number(s.total_value), 0)
);

// Z amounts (stored on DSR, editable here as local state for now)
const zAmountA = ref(0);
const zAmountB = ref(0);
const zAmountD = ref(0);

// ── Shortage Calculation ──────────────────────────────────────────────────────
const dipForm = useForm({
    tank_id:          '',
    dip_type:         'closing',
    dip_volume:       '',
    pump_test_volume: '',
});

function submitDip() {
    dipForm.post(route('tank-dips.store', props.shift.id), {
        onSuccess: () => dipForm.reset('dip_volume', 'pump_test_volume'),
    });
}

function getDip(tankId, type) {
    return props.shift.tank_dips?.find(d => d.tank_id == tankId && d.dip_type === type);
}

// Shortage calculation per tank (using nozzle→tank relationship)
const shortageRows = computed(() => {
    // Only show primary tanks (those that are not linked secondaries)
    const linkedIds = new Set(
        (props.station?.tanks ?? []).map(t => t.linked_tank_id).filter(Boolean)
    );
    return (props.station?.tanks ?? [])
        .filter(t => !linkedIds.has(t.id))
        .map(tank => {
            const linked    = props.station?.tanks?.find(t => t.id === tank.linked_tank_id) ?? null;

            const openDip   = getDip(tank.id, 'opening');
            const closeDip  = getDip(tank.id, 'closing');
            const openDip2  = linked ? getDip(linked.id, 'opening')  : null;
            const closeDip2 = linked ? getDip(linked.id, 'closing') : null;

            const opening   = Number(openDip?.dip_volume ?? 0) + Number(openDip2?.dip_volume ?? 0);
            const purchase  = tankDeliveries(tank.id) + (linked ? tankDeliveries(linked.id) : 0);
            const pumpTest  = Number(closeDip?.pump_test_volume ?? openDip?.pump_test_volume ?? 0);
            const subTotal  = opening + purchase - pumpTest;
            const sales     = meterSalesForTank(tank.id) + (linked ? meterSalesForTank(linked.id) : 0);
            const closing   = subTotal - sales;
            const dipStock  = Number(closeDip?.dip_volume ?? 0);
            const dipStock2 = Number(closeDip2?.dip_volume ?? 0);
            const totalDip  = dipStock + dipStock2;
            const shortage  = totalDip < closing ? closing - totalDip : 0;
            const excess    = totalDip > closing ? totalDip - closing : 0;

            // Nozzle breakdown for this tank
            const nozzleBreakdown = (props.station?.pump_nozzles ?? [])
                .filter(n => n.tank_id === tank.id || (linked && n.tank_id === linked.id))
                .map(n => ({
                    name:  n.nozzle_name,
                    sales: meterSalesForNozzle(n.id),
                }));

            return {
                tank, linked, opening, purchase, pumpTest, subTotal,
                sales, closing, dipStock, dipStock2, totalDip,
                shortage, excess, variance: totalDip - closing,
                nozzleBreakdown,
                price: getCurrentPrice(tank.product_id),
            };
        });
});

function tankDeliveries(tankId) {
    return (props.shift.deliveries ?? [])
        .filter(d => d.tank_id == tankId)
        .reduce((sum, d) => sum + Number(d.delivery_quantity ?? 0), 0);
}

function meterSalesForTank(tankId) {
    return (props.station?.pump_nozzles ?? [])
        .filter(n => n.tank_id === tankId)
        .reduce((sum, n) => sum + meterSalesForNozzle(n.id), 0);
}

function meterSalesForNozzle(nozzleId) {
    const r = getMeterByNozzle(nozzleId);
    return Number(r?.litres_sold ?? 0);
}

function meterSalesForProduct(productId) {
    return (props.shift.meter_readings ?? [])
        .filter(r => r.nozzle?.product_id == productId)
        .reduce((sum, r) => sum + Number(r.litres_sold ?? 0), 0);
}

// ── Client Sales ──────────────────────────────────────────────────────────────
const creditForm = useForm({
    credit_customer_id: '',
    product_id:         '',
    shift_id:           props.shift.id,
    type:               'fuel',
    quantity:           '',
    price_applied:      '',
    vehicle_plate:      '',
    notes:              '',
});

function onCreditProductChange() {
    const product = props.station?.products?.find(p => p.id == creditForm.product_id);
    if (product?.price_histories?.length) {
        creditForm.price_applied = product.price_histories[0].price_per_litre;
    }
}

function submitCreditSale() {
    creditForm.post(route('credit-sales.store'), {
        onSuccess: () => creditForm.reset('quantity', 'price_applied', 'vehicle_plate', 'notes'),
    });
}

const totalClientSales = computed(() =>
    (props.shift.credit_sales ?? []).reduce((sum, s) => sum + Number(s.total_value), 0)
);

// ── Cards ─────────────────────────────────────────────────────────────────────
const cardForm = useForm({
    card_name:  '',
    trans_date: props.shift.shift_date,
    reference:  '',
    amount:     '',
    recon_date: '',
    batch_ref:  '',
});

function submitCard() {
    cardForm.post(route('card-payments.store', props.shift.id), {
        onSuccess: () => cardForm.reset('reference', 'amount'),
    });
}

function deleteCard(id) {
    openConfirm({
        title: 'Remove Card Payment',
        message: 'Remove this card payment?',
        onConfirm: () => {
            router.delete(route('card-payments.destroy', id));
        },
    });
}

const totalCardSales = computed(() =>
    (props.shift.card_payments ?? []).reduce((sum, p) => sum + Number(p.amount), 0)
);

// ── POS ───────────────────────────────────────────────────────────────────────
const posForm = useForm({
    reference: '',
    amount:    '',
});

function submitPos() {
    posForm.post(route('pos-transactions.store', props.shift.id), {
        onSuccess: () => posForm.reset(),
    });
}

function deletePos(id) {
    openConfirm({
        title: 'Remove POS Transaction',
        message: 'Remove this POS transaction?',
        onConfirm: () => {
            router.delete(route('pos-transactions.destroy', id));
        },
    });
}

const totalPosSales = computed(() =>
    (props.shift.pos_transactions ?? []).reduce((sum, p) => sum + Number(p.amount), 0)
);

// ── Expenses ──────────────────────────────────────────────────────────────────
const expenseForm = useForm({
    expense_item: '',
    amount:       '',
});

function submitExpense() {
    expenseForm.post(route('expenses.store', props.shift.id), {
        onSuccess: () => expenseForm.reset(),
    });
}

function deleteExpense(id) {
    openConfirm({
        title: 'Remove Expense',
        message: 'Remove this expense?',
        onConfirm: () => {
            router.delete(route('expenses.destroy', id));
        },
    });
}

const totalExpenses = computed(() =>
    (props.shift.expenses ?? []).reduce((sum, e) => sum + Number(e.amount), 0)
);

// ── Sales Summary ─────────────────────────────────────────────────────────────

// Per-product fuel summary
const fuelSummary = computed(() => {
    const map = {};
    (props.station?.products ?? []).forEach(p => {
        map[p.id] = { name: p.product_name, litres: 0, revenue: 0, price: getCurrentPrice(p.id) };
    });
    (props.shift.meter_readings ?? []).forEach(r => {
        const pid = r.nozzle?.product_id;
        if (pid && map[pid]) {
            map[pid].litres  += Number(r.litres_sold ?? 0);
            map[pid].revenue += Number(r.litres_sold ?? 0) * map[pid].price;
        }
    });
    return Object.values(map).filter(p => p.litres > 0 || true);
});

const totalFuelLitres  = computed(() => fuelSummary.value.reduce((s, p) => s + p.litres, 0));
const totalFuelRevenue = computed(() => fuelSummary.value.reduce((s, p) => s + p.revenue, 0));

// Gross sales = all revenue channels
const grossSales = computed(() =>
    totalFuelRevenue.value + totalOilSales.value
);

// Net balance = gross - non-cash channels
const netSalesBalance = computed(() =>
    grossSales.value
    - totalClientSales.value
    - totalCardSales.value
    - totalPosSales.value
    - Number(cashForm.mpesa_amount || 0)
);

// ── Cash Reconciliation ───────────────────────────────────────────────────────
const cashForm = useForm({
    actual_cash:  props.shift.actual_cash  !== null ? String(props.shift.actual_cash)  : '',
    mpesa_amount: props.shift.mpesa_amount !== null ? String(props.shift.mpesa_amount) : '',
});

// Server-computed breakdown (recalculated on each page load / after save)
const recon = computed(() => props.cashReconciliation ?? {});

const cashVarianceStatus = computed(() => recon.value.variance_status ?? 'pending');

const varianceClass = computed(() => ({
    ok:       'text-green-700 bg-green-50',
    warning:  'text-yellow-800 bg-yellow-50',
    critical: 'text-red-700 bg-red-50',
    pending:  'text-gray-500 bg-gray-50',
}[cashVarianceStatus.value] ?? 'text-gray-500 bg-gray-50'));

const varianceBadgeClass = computed(() => ({
    ok:       'bg-green-100 text-green-700',
    warning:  'bg-yellow-100 text-yellow-800',
    critical: 'bg-red-100 text-red-700',
    pending:  'bg-gray-100 text-gray-500',
}[cashVarianceStatus.value] ?? 'bg-gray-100 text-gray-500'));

function saveCash() {
    cashForm.patch(route('shifts.update-cash', props.shift.id));
}

function generateDsr() {
    openConfirm({
        title: 'Finalise DSR',
        message: 'Generate DSR from current data?',
        onConfirm: () => {
            router.post(route('shifts.generate-dsr', props.shift.id));
        },
    });
}

</script>

<template>
    <Head :title="`${shift.shift_type === 'day' ? 'Day' : 'Night'} Shift — ${shift.shift_date}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">
                        {{ shift.shift_type === 'day' ? 'Day' : 'Night' }} Shift &mdash; {{ shift.shift_date }}
                    </h2>
                    <p class="text-sm text-gray-500 mt-0.5">
                        {{ station.station_name }} &bull;
                        Serial No: <span class="font-mono">{{ shift.daily_sales_record?.serial_number ?? '—' }}</span>
                        <span class="ml-3 px-2 py-0.5 rounded text-xs font-semibold"
                            :class="shift.status === 'locked' ? 'bg-red-100 text-red-700' : shift.status === 'closed' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'">
                            {{ shift.status.toUpperCase() }}
                        </span>
                    </p>
                </div>
                <div class="flex gap-2">
                    <button v-if="shift.status !== 'locked'" @click="generateDsr"
                        class="px-4 py-2 bg-blue-600 text-white rounded text-sm font-medium hover:bg-blue-700">
                        Finalise DSR
                    </button>
                    <Link v-if="shift.daily_sales_record" :href="route('dsr.show', shift.daily_sales_record.id)"
                        class="px-4 py-2 bg-gray-700 text-white rounded text-sm font-medium hover:bg-gray-800">
                        View DSR
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <!-- Locked banner -->
                <div v-if="isLocked"
                    class="mb-4 flex items-center gap-3 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-red-800">
                    <svg class="h-5 w-5 shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                    </svg>
                    <div>
                        <p class="font-semibold text-sm">This shift is locked — DSR has been finalised and approved.</p>
                        <p class="text-xs mt-0.5 text-red-600">All records are read-only. Use the Adjustments section on the DSR to record corrections.</p>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="border-b border-gray-200 mb-0">
                    <nav aria-label="Shift detail tabs" class="flex flex-wrap -mb-px">
                        <button v-for="tab in tabs" :key="tab.key"
                            @click="activeTab = tab.key"
                            class="px-4 py-2 text-sm font-medium border-b-2 mr-1 transition-colors inline-flex items-center"
                            :class="activeTab === tab.key
                                ? 'border-orange-500 text-orange-500'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
                            {{ tab.label }}
                            <span v-if="tabCounts[tab.key]"
                                class="ml-1.5 px-1.5 py-0.5 text-xs rounded-full"
                                :class="activeTab === tab.key ? 'bg-orange-400/20 text-orange-700' : 'bg-gray-100 text-gray-500'">
                                {{ tabCounts[tab.key] }}
                            </span>
                        </button>
                    </nav>
                </div>

                <div class="bg-white shadow rounded-b rounded-tr p-6">

                    <!-- ── Pump Readings ─────────────────────────────────── -->
                    <div v-show="activeTab === 'pumps'">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">
                                        <th class="px-3 py-2">Pump Name</th>
                                        <th class="px-3 py-2 text-right">Sales Qty Mechanical</th>
                                        <th class="px-3 py-2 text-right">Sales Qty Electrical</th>
                                        <th class="px-3 py-2 text-right">Sales Shs</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="nozzle in (station.pump_nozzles ?? [])" :key="nozzle.id"
                                        class="hover:bg-blue-50 cursor-pointer"
                                        @click="!isLocked && (meterForm.nozzle_id = String(nozzle.id), onNozzleSelect())">
                                        <td class="px-3 py-2 font-medium">
                                            {{ nozzle.nozzle_name }}
                                            <span class="text-xs text-gray-400 ml-1">{{ nozzle.tank?.tank_name }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-right font-mono">
                                            {{ fmt(getMeterByNozzle(nozzle.id)?.mechanical_sales ?? 0, 1) }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-mono">
                                            {{ fmt(getMeterByNozzle(nozzle.id)?.litres_sold ?? 0, 3) }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-mono">
                                            {{ fmt2(getMeterByNozzle(nozzle.id)?.shs_sold ?? 0) }}
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="font-semibold bg-gray-50">
                                        <td class="px-3 py-2">TOTAL</td>
                                        <td class="px-3 py-2 text-right font-mono"></td>
                                        <td class="px-3 py-2 text-right font-mono">{{ fmt(pumpSalesTotal, 3) }}</td>
                                        <td class="px-3 py-2 text-right font-mono">{{ fmt2(pumpRevenueTotal) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Entry form -->
                        <div v-if="!isLocked" class="mt-6 border-t pt-4">
                            <h3 class="text-sm font-semibold text-gray-700 mb-3">Enter Closing Reading</h3>
                            <form @submit.prevent="submitMeter" class="space-y-4">
                                <!-- Nozzle selector -->
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Pump Nozzle</label>
                                    <select v-model="meterForm.nozzle_id" @change="onNozzleSelect" required
                                        class="w-full border rounded px-3 py-2 text-sm">
                                        <option value="">— Select Nozzle —</option>
                                        <template v-for="(nozzles, productName) in nozzlesByProduct" :key="productName">
                                            <optgroup :label="productName">
                                                <option v-for="n in nozzles" :key="n.id" :value="String(n.id)">
                                                    {{ n.nozzle_name }}
                                                </option>
                                            </optgroup>
                                        </template>
                                    </select>
                                </div>

                                <!-- Opening / Closing grid -->
                                <div v-if="meterForm.nozzle_id" class="border rounded overflow-hidden">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                                <th class="px-3 py-2 text-left">Meter</th>
                                                <th class="px-3 py-2 text-right">Opening (auto)</th>
                                                <th class="px-3 py-2 text-right">Closing (enter)</th>
                                                <th class="px-3 py-2 text-right">Sales</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <tr>
                                                <td class="px-3 py-2 text-gray-600">Mechanical</td>
                                                <td class="px-3 py-2 text-right font-mono text-gray-400">
                                                    {{ selectedReading?.opening_mechanical != null
                                                        ? Number(selectedReading.opening_mechanical).toFixed(1)
                                                        : '—' }}
                                                </td>
                                                <td class="px-3 py-2 text-right">
                                                    <input v-model="meterForm.closing_mechanical" type="number" step="0.1" required
                                                        class="w-36 border rounded px-2 py-1 text-sm font-mono text-right" placeholder="0.0" />
                                                </td>
                                                <td class="px-3 py-2 text-right font-mono text-gray-500">
                                                    {{ (meterForm.closing_mechanical && selectedReading)
                                                        ? Number(meterForm.closing_mechanical - selectedReading.opening_mechanical).toFixed(1)
                                                        : '' }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="px-3 py-2 text-gray-600">Electrical</td>
                                                <td class="px-3 py-2 text-right font-mono text-gray-400">
                                                    {{ selectedReading?.opening_electrical != null
                                                        ? Number(selectedReading.opening_electrical).toFixed(3)
                                                        : '—' }}
                                                </td>
                                                <td class="px-3 py-2 text-right">
                                                    <input v-model="meterForm.closing_electrical" type="number" step="0.001" required
                                                        class="w-36 border rounded px-2 py-1 text-sm font-mono text-right" placeholder="0.000" />
                                                </td>
                                                <td class="px-3 py-2 text-right font-mono font-semibold">
                                                    {{ (meterForm.closing_electrical && selectedReading)
                                                        ? Number(meterForm.closing_electrical - selectedReading.opening_electrical).toFixed(3)
                                                        : '' }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="px-3 py-2 text-gray-600">Shs</td>
                                                <td class="px-3 py-2 text-right font-mono text-gray-400">
                                                    {{ selectedReading?.opening_shs != null
                                                        ? Number(selectedReading.opening_shs).toFixed(2)
                                                        : '—' }}
                                                </td>
                                                <td class="px-3 py-2 text-right">
                                                    <input v-model="meterForm.closing_shs" type="number" step="0.01"
                                                        class="w-36 border rounded px-2 py-1 text-sm font-mono text-right" placeholder="0.00" />
                                                </td>
                                                <td class="px-3 py-2 text-right font-mono text-gray-500">
                                                    {{ (meterForm.closing_shs && selectedReading?.opening_shs != null)
                                                        ? Number(meterForm.closing_shs - selectedReading.opening_shs).toFixed(2)
                                                        : '' }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div v-if="meterForm.nozzle_id" class="flex items-center gap-3">
                                    <button type="submit" :disabled="meterForm.processing"
                                        class="px-6 py-2 bg-blue-600 text-white rounded text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                                        Save Reading
                                    </button>
                                    <button v-if="selectedReading?.closing_electrical != null"
                                        type="button" @click="clearMeterReading"
                                        class="px-4 py-2 border border-red-300 text-red-600 rounded text-sm font-medium hover:bg-red-50">
                                        Clear Reading
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- ── Oils ─────────────────────────────────────────── -->
                    <div v-show="activeTab === 'oils'">
                        <div class="grid md:grid-cols-2 gap-6">
                            <div>
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">
                                            <th class="px-3 py-2">Item Name</th>
                                            <th class="px-3 py-2 text-right">Open Stock</th>
                                            <th class="px-3 py-2 text-right">Sales Qty</th>
                                            <th class="px-3 py-2 text-right">Close Stock</th>
                                            <th class="px-3 py-2 text-right">Unit Price</th>
                                            <th class="px-3 py-2 text-right">Sales Shs</th>
                                            <th class="px-3 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="sale in (shift.oil_sales ?? [])" :key="sale.id" class="hover:bg-gray-50">
                                            <td class="px-3 py-2">{{ sale.shop_product?.product_name }}</td>
                                            <td class="px-3 py-2 text-right font-mono">{{ fmt(sale.opening_stock, 0) }}</td>
                                            <td class="px-3 py-2 text-right font-mono">{{ fmt(sale.quantity, 0) }}</td>
                                            <td class="px-3 py-2 text-right font-mono">{{ fmt(sale.closing_stock, 0) }}</td>
                                            <td class="px-3 py-2 text-right font-mono">{{ fmt2(sale.unit_price) }}</td>
                                            <td class="px-3 py-2 text-right font-mono">{{ fmt2(sale.total_value) }}</td>
                                            <td class="px-3 py-2">
                                                <button v-if="!isLocked" @click="deleteOilSale(sale.id)"
                                                    aria-label="Delete oil sale"
                                                    class="p-1 rounded text-red-400 hover:text-red-600 hover:bg-red-50 transition-colors">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr v-if="!(shift.oil_sales ?? []).length">
                                            <td colspan="7" class="px-3 py-4 text-center text-gray-400 text-sm">No oil sales recorded</td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr class="font-semibold bg-gray-50">
                                            <td class="px-3 py-2" colspan="5">Total Oil Sales:</td>
                                            <td class="px-3 py-2 text-right font-mono">{{ fmt2(totalOilSales) }}</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>

                                <div v-if="!isLocked" class="mt-4 border-t pt-4">
                                    <form @submit.prevent="submitOilSale" class="space-y-3">
                                        <select v-model="oilForm.shop_product_id" @change="onShopProductSelect" required
                                            class="w-full border rounded px-3 py-2 text-sm">
                                            <option value="">— Select Product —</option>
                                            <option v-for="sp in (station.shop_products ?? [])" :key="sp.id" :value="String(sp.id)">
                                                {{ sp.product_name }}
                                            </option>
                                        </select>
                                        <div class="grid grid-cols-3 gap-2">
                                            <input v-model="oilForm.opening_stock" type="number" step="1" min="0" required
                                                placeholder="Opening Stock" class="border rounded px-3 py-2 text-sm font-mono" />
                                            <input v-model="oilForm.quantity" type="number" step="1" min="1" required
                                                placeholder="Qty Sold" class="border rounded px-3 py-2 text-sm font-mono" />
                                            <input v-model="oilForm.unit_price" type="number" step="0.01" required
                                                placeholder="Unit Price" class="border rounded px-3 py-2 text-sm font-mono" />
                                        </div>
                                        <button type="submit" :disabled="oilForm.processing"
                                            class="w-full px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 disabled:opacity-50">
                                            Insert
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div class="bg-gray-50 rounded p-4 space-y-3">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600">Z Amount A:</span>
                                        <input v-model="zAmountA" type="number" step="0.01"
                                            class="w-36 border rounded px-2 py-1 text-sm font-mono text-right" />
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600">Z Amount B:</span>
                                        <input v-model="zAmountB" type="number" step="0.01"
                                            class="w-36 border rounded px-2 py-1 text-sm font-mono text-right" />
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600">Z Amount D:</span>
                                        <input v-model="zAmountD" type="number" step="0.01"
                                            class="w-36 border rounded px-2 py-1 text-sm font-mono text-right" />
                                    </div>
                                </div>
                                <div class="text-sm space-y-1 pt-2">
                                    <div class="flex justify-between"><span>Total Oil Sales:</span><span class="font-mono font-semibold">{{ fmt2(totalOilSales) }}</span></div>
                                    <div class="flex justify-between text-gray-500"><span>Total Gas Sales:</span><span class="font-mono">0.00</span></div>
                                    <div class="flex justify-between text-gray-500"><span>Total Empty Gas Sales:</span><span class="font-mono">0.00</span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Shortage Calculation ──────────────────────────── -->
                    <div v-show="activeTab === 'shortage'">
                        <!-- Per-tank shortage section -->
                        <div v-for="row in shortageRows" :key="row.tank.id" class="mb-6 border rounded overflow-hidden">
                            <!-- Tank header -->
                            <div class="bg-gray-700 text-white px-4 py-2 flex items-center justify-between text-sm font-semibold">
                                <span>{{ row.tank.product?.product_name ?? row.tank.tank_name }}</span>
                                <span class="text-xs font-normal opacity-75">
                                    {{ row.tank.tank_name }}
                                    <span v-if="row.linked"> + {{ row.linked.tank_name }}</span>
                                </span>
                            </div>

                            <div class="flex">
                                <!-- Left: Shortage calculation table -->
                                <div class="flex-1 overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase">
                                                <th class="px-3 py-2 text-left">Item</th>
                                                <th class="px-3 py-2 text-right">Litres</th>
                                                <th class="px-3 py-2 text-right">Value (KES)</th>
                                            </tr>
                                        </thead>
                                        <tbody class="text-sm divide-y divide-gray-100">
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-3 py-1.5 text-gray-600">Opening Stock</td>
                                                <td class="px-3 py-1.5 text-right font-mono">{{ fmt(row.opening, 3) }}</td>
                                                <td class="px-3 py-1.5 text-right font-mono text-gray-400">{{ fmt2(row.opening * row.price) }}</td>
                                            </tr>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-3 py-1.5 text-gray-600">Purchase (Delivery)</td>
                                                <td class="px-3 py-1.5 text-right font-mono">{{ fmt(row.purchase, 3) }}</td>
                                                <td class="px-3 py-1.5 text-right font-mono text-gray-400">{{ fmt2(row.purchase * row.price) }}</td>
                                            </tr>
                                            <tr v-if="row.pumpTest > 0" class="hover:bg-gray-50">
                                                <td class="px-3 py-1.5 text-gray-600">Less: Pump Test</td>
                                                <td class="px-3 py-1.5 text-right font-mono text-orange-600">({{ fmt(row.pumpTest, 3) }})</td>
                                                <td class="px-3 py-1.5 text-right font-mono text-gray-400">({{ fmt2(row.pumpTest * row.price) }})</td>
                                            </tr>
                                            <tr class="bg-blue-50 font-semibold">
                                                <td class="px-3 py-1.5">Sub Total</td>
                                                <td class="px-3 py-1.5 text-right font-mono">{{ fmt(row.subTotal, 3) }}</td>
                                                <td class="px-3 py-1.5 text-right font-mono">{{ fmt2(row.subTotal * row.price) }}</td>
                                            </tr>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-3 py-1.5 text-gray-600">Less: Sales</td>
                                                <td class="px-3 py-1.5 text-right font-mono text-red-600">({{ fmt(row.sales, 3) }})</td>
                                                <td class="px-3 py-1.5 text-right font-mono text-gray-400">({{ fmt2(row.sales * row.price) }})</td>
                                            </tr>
                                            <tr class="bg-blue-50 font-semibold">
                                                <td class="px-3 py-1.5">Closing Stock (Calc.)</td>
                                                <td class="px-3 py-1.5 text-right font-mono">{{ fmt(row.closing, 3) }}</td>
                                                <td class="px-3 py-1.5 text-right font-mono">{{ fmt2(row.closing * row.price) }}</td>
                                            </tr>
                                            <!-- Dip readings -->
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-3 py-1.5 text-gray-600">
                                                    Dip Stock 1
                                                    <span class="text-xs text-gray-400">({{ row.tank.tank_name }})</span>
                                                </td>
                                                <td class="px-3 py-1.5 text-right font-mono">{{ fmt(row.dipStock, 3) }}</td>
                                                <td class="px-3 py-1.5 text-right font-mono text-gray-400">{{ fmt2(row.dipStock * row.price) }}</td>
                                            </tr>
                                            <tr v-if="row.linked" class="hover:bg-gray-50">
                                                <td class="px-3 py-1.5 text-gray-600">
                                                    Dip Stock 2
                                                    <span class="text-xs text-gray-400">({{ row.linked.tank_name }})</span>
                                                </td>
                                                <td class="px-3 py-1.5 text-right font-mono">{{ fmt(row.dipStock2, 3) }}</td>
                                                <td class="px-3 py-1.5 text-right font-mono text-gray-400">{{ fmt2(row.dipStock2 * row.price) }}</td>
                                            </tr>
                                            <tr v-if="row.linked" class="bg-gray-50 font-semibold">
                                                <td class="px-3 py-1.5">Total Dip Stock</td>
                                                <td class="px-3 py-1.5 text-right font-mono">{{ fmt(row.totalDip, 3) }}</td>
                                                <td class="px-3 py-1.5 text-right font-mono">{{ fmt2(row.totalDip * row.price) }}</td>
                                            </tr>
                                            <!-- Variance -->
                                            <tr v-if="row.shortage > 0" class="bg-red-50">
                                                <td class="px-3 py-1.5 font-semibold text-red-700">Shortage</td>
                                                <td class="px-3 py-1.5 text-right font-mono font-semibold text-red-700">{{ fmt(row.shortage, 3) }}</td>
                                                <td class="px-3 py-1.5 text-right font-mono font-semibold text-red-700">{{ fmt2(row.shortage * row.price) }}</td>
                                            </tr>
                                            <tr v-if="row.excess > 0" class="bg-green-50">
                                                <td class="px-3 py-1.5 font-semibold text-green-700">Excess</td>
                                                <td class="px-3 py-1.5 text-right font-mono font-semibold text-green-700">{{ fmt(row.excess, 3) }}</td>
                                                <td class="px-3 py-1.5 text-right font-mono font-semibold text-green-700">{{ fmt2(row.excess * row.price) }}</td>
                                            </tr>
                                            <tr v-if="row.shortage === 0 && row.excess === 0" class="bg-green-50">
                                                <td class="px-3 py-1.5 text-green-700 font-semibold" colspan="3">No Variance</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Right: Nozzle breakdown panel -->
                                <div class="w-48 border-l bg-gray-50 flex-shrink-0">
                                    <div class="px-3 py-2 bg-gray-100 text-xs font-semibold text-gray-600 uppercase tracking-wide">
                                        Nozzle Sales
                                    </div>
                                    <div v-for="nz in row.nozzleBreakdown" :key="nz.name"
                                        class="flex justify-between px-3 py-1.5 border-b border-gray-200 text-xs">
                                        <span class="text-gray-600 truncate mr-1">{{ nz.name }}</span>
                                        <span class="font-mono font-medium">{{ fmt(nz.sales, 3) }}</span>
                                    </div>
                                    <div class="flex justify-between px-3 py-2 bg-gray-100 text-xs font-bold border-t">
                                        <span>Total</span>
                                        <span class="font-mono">{{ fmt(row.sales, 3) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dip entry form -->
                        <div v-if="!isLocked" class="mt-2 border rounded p-4 bg-gray-50">
                            <h3 class="text-sm font-semibold text-gray-700 mb-3">Enter Tank Dip / Pump Test</h3>
                            <form @submit.prevent="submitDip" class="grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Tank</label>
                                    <select v-model="dipForm.tank_id" required class="w-full border rounded px-3 py-2 text-sm bg-white">
                                        <option value="">— Select Tank —</option>
                                        <option v-for="tank in (station.tanks ?? [])" :key="tank.id" :value="String(tank.id)">
                                            {{ tank.tank_name }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Dip Type</label>
                                    <select v-model="dipForm.dip_type" class="w-full border rounded px-3 py-2 text-sm bg-white">
                                        <option value="opening">Opening</option>
                                        <option value="closing">Closing</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Dip Volume (L)</label>
                                    <input v-model="dipForm.dip_volume" type="number" step="0.01" required
                                        class="w-full border rounded px-3 py-2 text-sm font-mono bg-white" placeholder="0.00" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Pump Test (L)</label>
                                    <input v-model="dipForm.pump_test_volume" type="number" step="0.001"
                                        class="w-full border rounded px-3 py-2 text-sm font-mono bg-white" placeholder="0.000" />
                                </div>
                                <div>
                                    <button type="submit" :disabled="dipForm.processing"
                                        class="w-full px-4 py-2 bg-blue-600 text-white rounded text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                                        Save Dip
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- ── Client Sales ──────────────────────────────────── -->
                    <div v-show="activeTab === 'clients'">
                        <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">
                                    <th class="px-3 py-2">Debit Note</th>
                                    <th class="px-3 py-2">Client Name</th>
                                    <th class="px-3 py-2">Vehicle</th>
                                    <th class="px-3 py-2">Product</th>
                                    <th class="px-3 py-2">Type</th>
                                    <th class="px-3 py-2 text-right">Qty (L)</th>
                                    <th class="px-3 py-2 text-right">Price</th>
                                    <th class="px-3 py-2 text-right">Amount</th>
                                    <th class="px-3 py-2 text-right">VAT</th>
                                    <th class="px-3 py-2 text-right">WHT</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="sale in (shift.credit_sales ?? [])" :key="sale.id" class="hover:bg-gray-50">
                                    <td class="px-3 py-2 font-mono text-xs text-gray-500">{{ sale.debit_note }}</td>
                                    <td class="px-3 py-2 font-medium">{{ sale.credit_customer?.customer_name }}</td>
                                    <td class="px-3 py-2 text-gray-500 text-xs">{{ sale.vehicle_plate ?? '—' }}</td>
                                    <td class="px-3 py-2 text-gray-500">{{ sale.product?.product_name }}</td>
                                    <td class="px-3 py-2">
                                        <span class="px-1.5 py-0.5 rounded text-xs font-medium"
                                            :class="sale.type === 'fuel' ? 'bg-blue-100 text-blue-700' : sale.type === 'oil' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600'">
                                            {{ sale.type }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono">{{ fmt(sale.quantity, 3) }}</td>
                                    <td class="px-3 py-2 text-right font-mono text-gray-500">{{ fmt2(sale.price_applied) }}</td>
                                    <td class="px-3 py-2 text-right font-mono font-semibold">{{ fmt2(sale.total_value) }}</td>
                                    <td class="px-3 py-2 text-right font-mono text-gray-500">{{ fmt2(sale.vat_amount) }}</td>
                                    <td class="px-3 py-2 text-right font-mono text-gray-500">{{ fmt2(sale.wht_amount) }}</td>
                                </tr>
                                <tr v-if="!(shift.credit_sales ?? []).length">
                                    <td colspan="10" class="px-3 py-4 text-center text-gray-400 text-sm">No credit sales</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="font-semibold bg-gray-50">
                                    <td class="px-3 py-2" colspan="7">Total Sales On Account:</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ fmt2(totalClientSales) }}</td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                        </div>

                        <div v-if="!isLocked" class="mt-6 border-t pt-4">
                            <h3 class="text-sm font-semibold text-gray-700 mb-3">Add Credit Sale</h3>
                            <form @submit.prevent="submitCreditSale" class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <div class="col-span-2">
                                    <label class="block text-xs text-gray-600 mb-1">Client</label>
                                    <select v-model="creditForm.credit_customer_id" required class="w-full border rounded px-3 py-2 text-sm">
                                        <option value="">— Select Client —</option>
                                        <option v-for="c in (station.credit_customers ?? [])" :key="c.id" :value="String(c.id)">
                                            {{ c.customer_name }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Product</label>
                                    <select v-model="creditForm.product_id" @change="onCreditProductChange" required
                                        class="w-full border rounded px-3 py-2 text-sm">
                                        <option value="">— Select Product —</option>
                                        <option v-for="p in (station.products ?? [])" :key="p.id" :value="String(p.id)">
                                            {{ p.product_name }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Type</label>
                                    <select v-model="creditForm.type" class="w-full border rounded px-3 py-2 text-sm">
                                        <option value="fuel">Fuel</option>
                                        <option value="oil">Oil</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Quantity (L)</label>
                                    <input v-model="creditForm.quantity" type="number" step="0.001" required
                                        class="w-full border rounded px-3 py-2 text-sm font-mono" placeholder="0.000" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Price/L</label>
                                    <input v-model="creditForm.price_applied" type="number" step="0.0001" required
                                        class="w-full border rounded px-3 py-2 text-sm font-mono" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Vehicle Plate</label>
                                    <input v-model="creditForm.vehicle_plate" type="text"
                                        class="w-full border rounded px-3 py-2 text-sm" placeholder="KAA 000A" />
                                </div>
                                <div class="flex items-end">
                                    <button type="submit" :disabled="creditForm.processing"
                                        class="w-full px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 disabled:opacity-50">
                                        Insert
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- ── Cards ─────────────────────────────────────────── -->
                    <div v-show="activeTab === 'cards'">
                        <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">
                                    <th class="px-3 py-2">Card Name</th>
                                    <th class="px-3 py-2">Trans Date</th>
                                    <th class="px-3 py-2">Ref</th>
                                    <th class="px-3 py-2 text-right">Amount</th>
                                    <th class="px-3 py-2">Recon Date</th>
                                    <th class="px-3 py-2">Batch Ref</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="payment in (shift.card_payments ?? [])" :key="payment.id" class="hover:bg-gray-50">
                                    <td class="px-3 py-2 font-medium">{{ payment.card_name }}</td>
                                    <td class="px-3 py-2 text-gray-500">{{ payment.trans_date }}</td>
                                    <td class="px-3 py-2 font-mono text-xs">{{ payment.reference }}</td>
                                    <td class="px-3 py-2 text-right font-mono font-semibold">{{ fmt2(payment.amount) }}</td>
                                    <td class="px-3 py-2 text-gray-400 text-xs">{{ payment.recon_date ?? '—' }}</td>
                                    <td class="px-3 py-2 font-mono text-xs text-gray-400">{{ payment.batch_ref ?? '—' }}</td>
                                    <td class="px-3 py-2">
                                        <button v-if="!isLocked" @click="deleteCard(payment.id)"
                                            aria-label="Delete card payment"
                                            class="p-1 rounded text-red-400 hover:text-red-600 hover:bg-red-50 transition-colors">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!(shift.card_payments ?? []).length">
                                    <td colspan="7" class="px-3 py-4 text-center text-gray-400 text-sm">No card payments</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="font-semibold bg-gray-50">
                                    <td class="px-3 py-2" colspan="3">Total Card Sales:</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ fmt2(totalCardSales) }}</td>
                                    <td colspan="3"></td>
                                </tr>
                            </tfoot>
                        </table>
                        </div>

                        <div v-if="!isLocked" class="mt-6 border-t pt-4">
                            <form @submit.prevent="submitCard" class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Card Name</label>
                                    <input v-model="cardForm.card_name" required list="card-names"
                                        class="w-full border rounded px-3 py-2 text-sm" placeholder="EQUITY" />
                                    <datalist id="card-names">
                                        <option value="EQUITY" /><option value="BARCLAYS" /><option value="KCB" />
                                        <option value="COOPERATIVE" /><option value="ABSA" /><option value="NCBA" />
                                        <option value="STANBIC" /><option value="STANDARD CHARTERED" />
                                    </datalist>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Trans Date</label>
                                    <input v-model="cardForm.trans_date" type="date" required
                                        class="w-full border rounded px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Reference</label>
                                    <input v-model="cardForm.reference" required
                                        class="w-full border rounded px-3 py-2 text-sm font-mono" placeholder="Transaction ref" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Amount</label>
                                    <input v-model="cardForm.amount" type="number" step="0.01" required
                                        class="w-full border rounded px-3 py-2 text-sm font-mono" placeholder="0.00" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Recon Date <span class="text-gray-400">(optional)</span></label>
                                    <input v-model="cardForm.recon_date" type="date"
                                        class="w-full border rounded px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Batch Ref <span class="text-gray-400">(optional)</span></label>
                                    <div class="flex gap-2">
                                        <input v-model="cardForm.batch_ref"
                                            class="flex-1 border rounded px-3 py-2 text-sm font-mono" placeholder="Bank batch ref" />
                                        <button type="submit" :disabled="cardForm.processing"
                                            class="px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 disabled:opacity-50">
                                            Insert
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- ── POS ───────────────────────────────────────────── -->
                    <div v-show="activeTab === 'pos'">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">
                                    <th class="px-3 py-2">Ref</th>
                                    <th class="px-3 py-2 text-right">Amount</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="tx in (shift.pos_transactions ?? [])" :key="tx.id" class="hover:bg-gray-50">
                                    <td class="px-3 py-2 font-mono">{{ tx.reference }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ fmt2(tx.amount) }}</td>
                                    <td class="px-3 py-2">
                                        <button v-if="!isLocked" @click="deletePos(tx.id)"
                                            aria-label="Delete POS transaction"
                                            class="p-1 rounded text-red-400 hover:text-red-600 hover:bg-red-50 transition-colors">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!(shift.pos_transactions ?? []).length">
                                    <td colspan="3" class="px-3 py-4 text-center text-gray-400 text-sm">No POS transactions</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="font-semibold bg-gray-50">
                                    <td class="px-3 py-2">Total POS Sales:</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ fmt2(totalPosSales) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>

                        <div v-if="!isLocked" class="mt-6 border-t pt-4">
                            <form @submit.prevent="submitPos" class="flex gap-3">
                                <div class="flex-1">
                                    <label class="block text-xs text-gray-600 mb-1">POS Reference</label>
                                    <input v-model="posForm.reference" required
                                        class="w-full border rounded px-3 py-2 text-sm font-mono" placeholder="Slip reference" />
                                </div>
                                <div class="flex-1">
                                    <label class="block text-xs text-gray-600 mb-1">Amount</label>
                                    <input v-model="posForm.amount" type="number" step="0.01" required
                                        class="w-full border rounded px-3 py-2 text-sm font-mono" placeholder="0.00" />
                                </div>
                                <div class="flex items-end">
                                    <button type="submit" :disabled="posForm.processing"
                                        class="px-6 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 disabled:opacity-50">
                                        Insert
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- ── Expenses ──────────────────────────────────────── -->
                    <div v-show="activeTab === 'expenses'">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">
                                    <th class="px-3 py-2">Expense Item</th>
                                    <th class="px-3 py-2 text-right">Amount</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="expense in (shift.expenses ?? [])" :key="expense.id" class="hover:bg-gray-50">
                                    <td class="px-3 py-2">{{ expense.expense_item }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ fmt2(expense.amount) }}</td>
                                    <td class="px-3 py-2">
                                        <button v-if="!isLocked" @click="deleteExpense(expense.id)"
                                            aria-label="Delete expense"
                                            class="text-red-500 hover:text-red-700 transition-colors">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!(shift.expenses ?? []).length">
                                    <td colspan="3" class="px-3 py-4 text-center text-gray-400 text-sm">No expenses recorded</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="font-semibold bg-gray-50">
                                    <td class="px-3 py-2">Total Expenses:</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ fmt2(totalExpenses) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>

                        <div v-if="!isLocked" class="mt-6 border-t pt-4">
                            <form @submit.prevent="submitExpense" class="flex gap-3">
                                <div class="flex-1">
                                    <label class="block text-xs text-gray-600 mb-1">Expense Item</label>
                                    <input v-model="expenseForm.expense_item" required
                                        class="w-full border rounded px-3 py-2 text-sm" placeholder="Description" />
                                </div>
                                <div class="w-40">
                                    <label class="block text-xs text-gray-600 mb-1">Amount</label>
                                    <input v-model="expenseForm.amount" type="number" step="0.01" required
                                        class="w-full border rounded px-3 py-2 text-sm font-mono" placeholder="0.00" />
                                </div>
                                <div class="flex items-end">
                                    <button type="submit" :disabled="expenseForm.processing"
                                        class="px-6 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 disabled:opacity-50">
                                        Insert
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- ── Sales Summary ─────────────────────────────────── -->
                    <div v-show="activeTab === 'summary'">
                        <div class="max-w-lg mx-auto space-y-1 text-sm">
                            <!-- Fuel breakdown -->
                            <table class="w-full mb-4">
                                <thead>
                                    <tr class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                        <th class="py-1 text-left">Product</th>
                                        <th class="py-1 text-right">Sales Amount</th>
                                        <th class="py-1 text-right">Sales Qty</th>
                                        <th class="py-1 text-right">Purchases</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="row in fuelSummary" :key="row.name" class="text-gray-700">
                                        <td class="py-1 uppercase">{{ row.name }}</td>
                                        <td class="py-1 text-right font-mono">{{ row.litres > 0 ? fmt2(row.revenue) : '' }}</td>
                                        <td class="py-1 text-right font-mono">{{ row.litres > 0 ? fmt(row.litres, 3) : '' }}</td>
                                        <td class="py-1 text-right font-mono"></td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="font-bold border-t border-gray-300">
                                        <td class="py-2">Total Fuel Sales:</td>
                                        <td class="py-2 text-right font-mono">{{ fmt2(totalFuelRevenue) }}</td>
                                        <td class="py-2 text-right font-mono">{{ fmt(totalFuelLitres, 3) }}</td>
                                        <td class="py-2 text-right font-mono">0.00</td>
                                    </tr>
                                </tfoot>
                            </table>

                            <!-- Revenue channels -->
                            <div class="space-y-1 pt-2 border-t text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Total Oil/Shop Sales:</span>
                                    <span class="font-mono">{{ fmt2(totalOilSales) }}</span>
                                </div>
                                <div class="flex justify-between font-semibold border-t pt-1">
                                    <span>Gross Sales:</span>
                                    <span class="font-mono">{{ fmt2(grossSales) }}</span>
                                </div>
                            </div>

                            <!-- Non-cash deductions -->
                            <div class="space-y-1 pt-2 border-t text-sm">
                                <p class="text-xs text-gray-400 uppercase tracking-wide font-medium mb-1">Non-Cash Channels</p>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Sales On Account (Credit):</span>
                                    <span class="font-mono text-gray-700">{{ fmt2(totalClientSales) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Card Payments:</span>
                                    <span class="font-mono text-gray-700">{{ fmt2(totalCardSales) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">POS:</span>
                                    <span class="font-mono text-gray-700">{{ fmt2(totalPosSales) }}</span>
                                </div>
                                <!-- MPESA — non-cash, must be entered to compute expected cash -->
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">MPESA:</span>
                                    <input v-if="!isLocked" v-model="cashForm.mpesa_amount" type="number" step="0.01" min="0"
                                        class="w-36 border rounded px-2 py-1 text-sm font-mono text-right"
                                        placeholder="0.00" />
                                    <span v-else class="font-mono">{{ fmt2(recon.mpesa_amount) }}</span>
                                </div>
                                <div class="flex justify-between font-semibold border-t pt-1"
                                    :class="netSalesBalance < 0 ? 'text-red-600' : 'text-green-700'">
                                    <span>Net Cash Balance:</span>
                                    <span class="font-mono">
                                        {{ netSalesBalance < 0 ? `(${fmt2(Math.abs(netSalesBalance))})` : fmt2(netSalesBalance) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Expenses -->
                            <div class="flex justify-between pt-2 border-t text-sm">
                                <span class="text-gray-600">Cash Expenses:</span>
                                <span class="font-mono text-red-600">{{ fmt2(totalExpenses) }}</span>
                            </div>

                            <!-- ── Cash Reconciliation ── -->
                            <div class="mt-4 rounded-lg border-2 p-4 text-sm"
                                :class="{
                                    'border-gray-200 bg-gray-50': cashVarianceStatus === 'pending',
                                    'border-green-300 bg-green-50': cashVarianceStatus === 'ok',
                                    'border-yellow-300 bg-yellow-50': cashVarianceStatus === 'warning',
                                    'border-red-300 bg-red-50': cashVarianceStatus === 'critical',
                                }">
                                <div class="flex items-center justify-between mb-3">
                                    <p class="font-semibold text-gray-700">Cash Drawer Reconciliation</p>
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold" :class="varianceBadgeClass">
                                        {{ cashVarianceStatus === 'pending' ? 'NOT COUNTED' : cashVarianceStatus.toUpperCase() }}
                                    </span>
                                </div>

                                <!-- Expected cash breakdown -->
                                <div class="space-y-1 text-xs text-gray-600 mb-3 pb-3 border-b border-gray-200">
                                    <div class="flex justify-between">
                                        <span>Fuel Cash Sales:</span>
                                        <span class="font-mono">{{ fmt2(recon.fuel_cash_sales ?? 0) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Oil/Shop Cash Sales:</span>
                                        <span class="font-mono">{{ fmt2(recon.oil_cash_sales ?? 0) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Cash Receipts from Customers:</span>
                                        <span class="font-mono">{{ fmt2(recon.cash_payments_received ?? 0) }}</span>
                                    </div>
                                    <div class="flex justify-between text-red-600">
                                        <span>Less: Cash Expenses:</span>
                                        <span class="font-mono">({{ fmt2(recon.cash_expenses ?? 0) }})</span>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <!-- Expected cash (calculated) -->
                                    <div class="flex justify-between font-semibold text-gray-800">
                                        <span>Expected Cash in Drawer:</span>
                                        <span class="font-mono text-base">KES {{ fmt2(recon.expected_cash ?? 0) }}</span>
                                    </div>

                                    <!-- Actual cash input -->
                                    <div class="flex justify-between items-center">
                                        <span class="font-semibold text-gray-800">Actual Cash Counted:</span>
                                        <input v-if="!isLocked" v-model="cashForm.actual_cash" type="number" step="0.01" min="0"
                                            class="w-36 border-2 border-gray-300 rounded px-2 py-1 text-sm font-mono text-right font-semibold focus:border-blue-400"
                                            placeholder="Enter count" />
                                        <span v-else class="font-mono font-semibold text-base">KES {{ fmt2(recon.actual_cash) }}</span>
                                    </div>

                                    <!-- Variance (shown only when actual entered) -->
                                    <div v-if="recon.variance !== null && recon.variance !== undefined"
                                        class="flex justify-between font-bold text-base pt-2 border-t"
                                        :class="varianceClass">
                                        <span>Variance:</span>
                                        <span class="font-mono">
                                            {{ recon.variance < 0 ? `(${fmt2(Math.abs(recon.variance))})` : fmt2(recon.variance) }}
                                            <span class="text-xs font-normal ml-1">({{ recon.variance_pct?.toFixed(1) }}%)</span>
                                        </span>
                                    </div>

                                    <!-- Missing price warning -->
                                    <p v-if="recon.missing_prices?.length" class="text-xs text-amber-700 mt-1">
                                        ⚠ No price set for: {{ recon.missing_prices.join(', ') }} — fuel cash sales may be understated.
                                    </p>

                                    <!-- Save button -->
                                    <button v-if="!isLocked" @click="saveCash"
                                        :disabled="cashForm.processing || !cashForm.actual_cash"
                                        class="mt-2 w-full py-1.5 rounded text-sm font-medium text-white transition-colors"
                                        :class="cashForm.actual_cash ? 'bg-blue-600 hover:bg-blue-700' : 'bg-gray-300 cursor-not-allowed'">
                                        {{ cashForm.processing ? 'Saving…' : 'Save Cash Count' }}
                                    </button>
                                    <p v-if="cashForm.errors.actual_cash" class="text-xs text-red-600">{{ cashForm.errors.actual_cash }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- end card -->

                <!-- Bottom bar -->
                <div class="mt-4 flex items-center justify-between text-sm text-gray-500 px-1">
                    <div>
                        Prepared By: <strong>{{ shift.opened_by?.name ?? '—' }}</strong>
                        &nbsp;&nbsp; Verified By: <strong>{{ shift.daily_sales_record?.verified_by ?? '—' }}</strong>
                    </div>
                    <div>
                        DSR Covers <strong>{{ shift.daily_sales_record?.dsr_covers_days ?? 1 }}</strong> day(s)
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
