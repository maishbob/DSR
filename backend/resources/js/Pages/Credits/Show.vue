<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    customer:        Object,
    transactions:    Array,
    brought_forward: Number,
    from_date:       String,
    to_date:         String,
});

// ── Edit customer modal ──────────────────────────────────────
const showEdit = ref(false);

const editForm = useForm({
    customer_name:            props.customer.customer_name,
    contact:                  props.customer.contact ?? '',
    phone:                    props.customer.phone ?? '',
    email:                    props.customer.email ?? '',
    address:                  props.customer.address ?? '',
    city:                     props.customer.city ?? '',
    pin:                      props.customer.pin ?? '',
    vat_number:               props.customer.vat_number ?? '',
    is_withholding_vat_agent: props.customer.is_withholding_vat_agent ?? false,
    credit_limit:             props.customer.credit_limit,
    discount_multiplier:      props.customer.discount_multiplier ?? '',
    initial_opening_balance:  props.customer.initial_opening_balance ?? '',
    is_active:                props.customer.is_active,
});

function submitEdit() {
    editForm.put(route('credits.update', props.customer.id), {
        onSuccess: () => { showEdit.value = false; },
    });
}

// ── Payment form ─────────────────────────────────────────────
const showPayment = ref(false);

const paymentForm = useForm({
    payment_date:   new Date().toISOString().slice(0, 10),
    receipt_no:     '',
    trans_type:     'receipts',
    amount:         '',
    payment_method: 'cash',
    reference:      '',
    notes:          '',
});

function submitPayment() {
    paymentForm.post(route('credits.payments.store', props.customer.id), {
        onSuccess: () => { showPayment.value = false; paymentForm.reset(); },
    });
}

// ── Date filter ───────────────────────────────────────────────
const filterFrom = ref(props.from_date ?? '');
const filterTo   = ref(props.to_date ?? '');

function applyFilter() {
    router.get(route('credits.show', props.customer.id), {
        from_date: filterFrom.value || undefined,
        to_date:   filterTo.value   || undefined,
    }, { preserveScroll: true });
}

function clearFilter() {
    filterFrom.value = '';
    filterTo.value   = '';
    router.get(route('credits.show', props.customer.id), {}, { preserveScroll: true });
}

// ── Running balance computation ───────────────────────────────
const ledgerRows = computed(() => {
    let running = (props.brought_forward ?? 0);
    return props.transactions.map(tx => {
        running += (tx.debit ?? 0) - (tx.credit ?? 0);
        return { ...tx, running };
    });
});

const closingBalance = computed(() => {
    const last = ledgerRows.value[ledgerRows.value.length - 1];
    return last ? last.running : (props.brought_forward ?? 0);
});

// ── Helpers ────────────────────────────────────────────────────
function fmt(n, dec = 2) {
    return Number(n ?? 0).toLocaleString('en-KE', { minimumFractionDigits: dec, maximumFractionDigits: dec });
}
function fmtDate(d) {
    return d ? new Date(d + 'T00:00:00').toLocaleDateString('en-KE', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
}

const saleTypeLabel = { fuel: 'Fuel', oil: 'Oil', other: 'Other' };

function deleteSale(row) {
    if (!confirm(`Delete credit sale of KES ${fmt(row.debit)} — ${row.description}?`)) return;
    // row.id is formatted as "sale_123" — extract the numeric id
    const id = String(row.id).replace('sale_', '');
    router.delete(route('credit-sales.destroy', id), { preserveScroll: true });
}
</script>

<template>
    <Head :title="customer.customer_name + ' — Statement'" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('credits.index')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-xl font-semibold text-gray-800">{{ customer.customer_name }}</h1>
                <span v-if="!customer.is_active"
                    class="px-2 py-0.5 text-xs rounded-full bg-gray-200 text-gray-500">Inactive</span>
            </div>
        </template>

        <!-- ── Customer details card ── -->
        <div class="bg-white rounded-xl shadow-sm p-5 mb-5">
            <div class="flex items-start justify-between">
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-8 gap-y-2 text-sm">
                    <div v-if="customer.contact">
                        <span class="text-gray-400 text-xs">Contact</span>
                        <p class="font-medium text-gray-800">{{ customer.contact }}</p>
                    </div>
                    <div v-if="customer.phone">
                        <span class="text-gray-400 text-xs">Phone</span>
                        <p class="font-medium text-gray-800">{{ customer.phone }}</p>
                    </div>
                    <div v-if="customer.email">
                        <span class="text-gray-400 text-xs">Email</span>
                        <p class="font-medium text-gray-800">{{ customer.email }}</p>
                    </div>
                    <div v-if="customer.city">
                        <span class="text-gray-400 text-xs">City</span>
                        <p class="font-medium text-gray-800">{{ customer.city }}</p>
                    </div>
                    <div v-if="customer.address">
                        <span class="text-gray-400 text-xs">Address</span>
                        <p class="font-medium text-gray-800">{{ customer.address }}</p>
                    </div>
                    <div v-if="customer.pin">
                        <span class="text-gray-400 text-xs">KRA PIN</span>
                        <p class="font-medium text-gray-800 font-mono">{{ customer.pin }}</p>
                    </div>
                    <div v-if="customer.vat_number">
                        <span class="text-gray-400 text-xs">VAT No.</span>
                        <p class="font-medium text-gray-800 font-mono">{{ customer.vat_number }}</p>
                    </div>
                    <div>
                        <span class="text-gray-400 text-xs">Credit Limit</span>
                        <p class="font-medium text-gray-800">KES {{ fmt(customer.credit_limit) }}</p>
                    </div>
                    <div v-if="customer.discount_multiplier > 0">
                        <span class="text-gray-400 text-xs">Discount</span>
                        <p class="font-medium text-gray-800">{{ fmt(customer.discount_multiplier * 100, 2) }}%</p>
                    </div>
                    <div v-if="customer.is_withholding_vat_agent">
                        <span class="text-gray-400 text-xs">WHT VAT</span>
                        <p class="font-medium text-orange-600">Yes — WHT Agent</p>
                    </div>
                </div>
                <button @click="showEdit = true"
                    class="text-xs text-orange-600 hover:underline border border-orange-200 px-3 py-1.5 rounded-lg ml-4 whitespace-nowrap">
                    Edit Details
                </button>
            </div>
        </div>

        <!-- ── Summary strip ── -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4"
                :class="closingBalance > 0 ? 'border-red-500' : 'border-green-500'">
                <p class="text-xs text-gray-400">{{ from_date ? 'Period' : 'Running' }} Balance</p>
                <p class="text-xl font-bold" :class="closingBalance > 0 ? 'text-red-600' : 'text-green-600'">
                    KES {{ fmt(Math.abs(closingBalance)) }}
                    <span class="text-sm font-normal">{{ closingBalance < 0 ? 'CR' : 'DR' }}</span>
                </p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-xs text-gray-400">Opening Balance{{ from_date ? ' (B/F)' : '' }}</p>
                <p class="text-lg font-bold text-gray-700">KES {{ fmt(brought_forward) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-xs text-gray-400">Period Sales (Dr)</p>
                <p class="text-lg font-bold text-red-600">
                    KES {{ fmt(transactions.reduce((s, t) => s + (t.debit ?? 0), 0)) }}
                </p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-xs text-gray-400">Period Receipts (Cr)</p>
                <p class="text-lg font-bold text-green-600">
                    KES {{ fmt(transactions.reduce((s, t) => s + (t.credit ?? 0), 0)) }}
                </p>
            </div>
        </div>

        <!-- ── Toolbar ── -->
        <div class="flex flex-wrap items-end gap-3 mb-4">
            <!-- Date filter -->
            <div class="flex items-end gap-2 bg-white rounded-xl shadow-sm px-4 py-3">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">From</label>
                    <input type="date" v-model="filterFrom"
                        class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm" />
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">To</label>
                    <input type="date" v-model="filterTo"
                        class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm" />
                </div>
                <button @click="applyFilter"
                    class="bg-gray-700 text-white px-3 py-1.5 rounded-lg text-sm hover:bg-gray-800">
                    Filter
                </button>
                <button v-if="from_date || to_date" @click="clearFilter"
                    class="text-sm text-gray-500 hover:underline px-2">
                    Clear
                </button>
            </div>

            <div class="flex-1"></div>

            <Link :href="route('payments.index')"
                class="border border-gray-300 text-gray-600 px-4 py-2 rounded-lg text-sm hover:bg-gray-50">
                All Payments
            </Link>
            <button @click="showPayment = !showPayment"
                class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 font-medium">
                + Record Payment
            </button>
        </div>

        <!-- ── Payment form ── -->
        <div v-if="showPayment" class="bg-white rounded-xl shadow-sm p-5 mb-5">
            <h2 class="font-semibold text-gray-700 mb-4">Record Payment</h2>
            <form @submit.prevent="submitPayment" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Date</label>
                    <input type="date" v-model="paymentForm.payment_date" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Receipt / Invoice No</label>
                    <input type="text" v-model="paymentForm.receipt_no"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono"
                        placeholder="e.g. 60092" />
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Trans Type</label>
                    <select v-model="paymentForm.trans_type"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="receipts">Receipts</option>
                        <option value="fuel">Fuel</option>
                        <option value="lpg">LPG</option>
                        <option value="pos">POS</option>
                        <option value="invoice">Invoice</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Chq No / Reference</label>
                    <input type="text" v-model="paymentForm.reference"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono"
                        placeholder="e.g. 258080" />
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Payment Method</label>
                    <select v-model="paymentForm.payment_method"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="cash">Cash</option>
                        <option value="mpesa">M-Pesa</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="cheque">Cheque</option>
                        <option value="rtgs">RTGS</option>
                        <option value="equity_card">Equity Card</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Amount (KES)</label>
                    <input type="number" v-model="paymentForm.amount" step="0.01" required min="0.01"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">Notes</label>
                    <input type="text" v-model="paymentForm.notes"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" :disabled="paymentForm.processing"
                        class="flex-1 bg-green-600 text-white py-2 rounded-lg text-sm hover:bg-green-700 disabled:opacity-60">
                        Save
                    </button>
                    <button type="button" @click="showPayment = false"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>

        <!-- ── Statement ledger ── -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-semibold text-gray-700">
                    Account Statement
                    <span v-if="from_date" class="font-normal text-gray-400 text-sm ml-2">
                        {{ fmtDate(from_date) }} – {{ to_date ? fmtDate(to_date) : 'today' }}
                    </span>
                </h2>
                <span class="text-xs text-gray-400">{{ transactions.length }} transaction(s)</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-gray-500 font-medium">Date</th>
                            <th class="px-4 py-3 text-left text-gray-500 font-medium">Description</th>
                            <th class="px-4 py-3 text-left text-gray-500 font-medium">Ref / DSR</th>
                            <th class="px-4 py-3 text-right text-gray-500 font-medium">Qty</th>
                            <th class="px-4 py-3 text-right text-gray-500 font-medium">Debit (KES)</th>
                            <th class="px-4 py-3 text-right text-gray-500 font-medium">Credit (KES)</th>
                            <th class="px-4 py-3 text-right text-gray-500 font-medium">Balance (KES)</th>
                            <th class="px-4 py-3 w-8"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Brought forward row -->
                        <tr class="bg-amber-50 border-b border-amber-200">
                            <td class="px-4 py-2.5 text-gray-500 italic text-xs" colspan="3">
                                {{ from_date ? 'Balance Brought Forward' : 'Opening Balance' }}
                            </td>
                            <td colspan="3"></td>
                            <td class="px-4 py-2.5 text-right font-semibold text-gray-700">
                                {{ fmt(brought_forward) }}
                            </td>
                        </tr>

                        <!-- Transaction rows -->
                        <tr v-for="row in ledgerRows" :key="row.id"
                            class="border-t border-gray-100"
                            :class="row.type === 'payment' ? 'bg-green-50/40' : 'hover:bg-gray-50'">
                            <td class="px-4 py-2.5 text-gray-600 whitespace-nowrap">{{ fmtDate(row.date) }}</td>
                            <td class="px-4 py-2.5">
                                <span class="font-medium text-gray-800">{{ row.description }}</span>
                                <span v-if="row.sale_type"
                                    class="ml-1.5 px-1.5 py-0.5 text-xs rounded bg-orange-100 text-orange-700">
                                    {{ saleTypeLabel[row.sale_type] ?? row.sale_type }}
                                </span>
                                <span v-if="row.type === 'payment'"
                                    class="ml-1.5 px-1.5 py-0.5 text-xs rounded bg-green-100 text-green-700">
                                    Receipt
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-gray-400 text-xs font-mono">{{ row.reference ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-right text-gray-500">
                                <span v-if="row.quantity">{{ fmt(row.quantity, 3) }} L</span>
                                <span v-else>—</span>
                            </td>
                            <td class="px-4 py-2.5 text-right font-medium text-red-600">
                                {{ row.debit != null ? fmt(row.debit) : '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-right font-medium text-green-700">
                                {{ row.credit != null ? fmt(row.credit) : '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-right font-semibold"
                                :class="row.running > 0 ? 'text-red-700' : 'text-green-700'">
                                {{ fmt(Math.abs(row.running)) }}
                                <span class="text-xs font-normal ml-0.5">{{ row.running < 0 ? 'CR' : 'DR' }}</span>
                            </td>
                            <td class="px-2 py-2.5 text-center">
                                <button v-if="row.type === 'sale'"
                                    @click="deleteSale(row)"
                                    class="text-gray-300 hover:text-red-500 transition-colors"
                                    title="Delete sale">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <!-- Empty state -->
                        <tr v-if="!transactions?.length">
                            <td colspan="8" class="px-4 py-10 text-center text-gray-400">
                                No transactions{{ from_date ? ' in this period' : '' }}.
                            </td>
                        </tr>

                        <!-- Closing balance footer -->
                        <tr v-if="transactions?.length" class="bg-gray-100 border-t-2 border-gray-300 font-semibold">
                            <td colspan="4" class="px-4 py-3 text-gray-600">Closing Balance</td>

                            <td class="px-4 py-3 text-right text-red-600">
                                {{ fmt(transactions.reduce((s, t) => s + (t.debit ?? 0), 0)) }}
                            </td>
                            <td class="px-4 py-3 text-right text-green-700">
                                {{ fmt(transactions.reduce((s, t) => s + (t.credit ?? 0), 0)) }}
                            </td>
                            <td class="px-4 py-3 text-right"
                                :class="closingBalance > 0 ? 'text-red-700' : 'text-green-700'">
                                {{ fmt(Math.abs(closingBalance)) }}
                                <span class="text-xs font-normal ml-0.5">{{ closingBalance < 0 ? 'CR' : 'DR' }}</span>
                            </td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── Edit customer modal ── -->
        <div v-if="showEdit"
            class="fixed inset-0 bg-black/50 flex items-start justify-center z-50 p-4 overflow-y-auto">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl my-8">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h2 class="font-semibold text-gray-800">Edit Customer Details</h2>
                    <button @click="showEdit = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <form @submit.prevent="submitEdit" class="p-6 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Customer Name *</label>
                            <input type="text" v-model="editForm.customer_name" required
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                            <p v-if="editForm.errors.customer_name" class="text-red-500 text-xs mt-1">{{ editForm.errors.customer_name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Contact Person</label>
                            <input type="text" v-model="editForm.contact"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Phone</label>
                            <input type="tel" v-model="editForm.phone"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Email</label>
                            <input type="email" v-model="editForm.email"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">City / Town</label>
                            <input type="text" v-model="editForm.city"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Address</label>
                            <input type="text" v-model="editForm.address"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">KRA PIN</label>
                            <input type="text" v-model="editForm.pin"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono"
                                placeholder="P123456789A" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">VAT Number</label>
                            <input type="text" v-model="editForm.vat_number"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Credit Limit (KES) *</label>
                            <input type="number" v-model="editForm.credit_limit" required min="0" step="0.01"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Opening Balance (KES)</label>
                            <input type="number" v-model="editForm.initial_opening_balance" step="0.01"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Discount Multiplier (0–1)</label>
                            <input type="number" v-model="editForm.discount_multiplier" min="0" max="1" step="0.0001"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                                placeholder="e.g. 0.05 = 5%" />
                        </div>
                        <div class="flex flex-col gap-3 pt-1">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" v-model="editForm.is_withholding_vat_agent"
                                    class="w-4 h-4 accent-orange-500" />
                                <span class="text-sm text-gray-700">Withholding VAT Agent</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" v-model="editForm.is_active"
                                    class="w-4 h-4 accent-green-500" />
                                <span class="text-sm text-gray-700">Active Account</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                        <button type="button" @click="showEdit = false"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-sm">
                            Cancel
                        </button>
                        <button type="submit" :disabled="editForm.processing"
                            class="bg-orange-500 text-white px-6 py-2 rounded-lg text-sm hover:bg-orange-600 disabled:opacity-60">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
