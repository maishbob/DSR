<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { fmt, fmtDate } from '@/composables/useFormatters';

const props = defineProps({
    payments: Object,
});

// ── Confirm modal ─────────────────────────────────────────────
const confirmModal = ref({ show: false, title: '', message: '', variant: 'danger', onConfirm: () => {} });
function openConfirm({ title, message, variant = 'danger', onConfirm }) {
    confirmModal.value = { show: true, title, message, variant, onConfirm };
}
function closeConfirm() { confirmModal.value.show = false; }
function handleConfirm() { confirmModal.value.onConfirm(); closeConfirm(); }

// ── Edit modal ────────────────────────────────────────────────
const editTarget = ref(null);

const editForm = useForm({
    payment_date:   '',
    receipt_no:     '',
    trans_type:     'receipts',
    amount:         '',
    payment_method: 'cash',
    reference:      '',
    notes:          '',
});

function openEdit(p) {
    editTarget.value = p;
    editForm.payment_date   = p.payment_date?.slice(0, 10) ?? '';
    editForm.receipt_no     = p.receipt_no ?? '';
    editForm.trans_type     = p.trans_type ?? 'receipts';
    editForm.amount         = p.amount;
    editForm.payment_method = p.payment_method ?? 'cash';
    editForm.reference      = p.reference ?? '';
    editForm.notes          = p.notes ?? '';
}

function submitEdit() {
    editForm.put(route('payments.update', editTarget.value.id), {
        onSuccess: () => { editTarget.value = null; },
    });
}

function deletePayment(p) {
    openConfirm({
        title: 'Delete Payment',
        message: `Delete payment of KES ${fmt(p.amount)} for ${p.customer_name}?`,
        onConfirm: () => router.delete(route('payments.destroy', p.id), { preserveScroll: true }),
    });
}

// ── Trans type labels ─────────────────────────────────────────
const transTypeLabel = {
    receipts: 'Receipts',
    fuel:     'Fuel',
    lpg:      'LPG',
    pos:      'POS',
    invoice:  'Invoice',
};

const methodLabel = {
    cash:           'Cash',
    mpesa:          'M-Pesa',
    bank_transfer:  'Bank Transfer',
    cheque:         'Cheque',
    rtgs:           'RTGS',
    equity_card:    'Equity Card',
    other:          'Other',
};


const transTypeColor = {
    receipts: 'bg-green-100 text-green-700',
    fuel:     'bg-orange-100 text-orange-700',
    lpg:      'bg-blue-100 text-blue-700',
    pos:      'bg-purple-100 text-purple-700',
    invoice:  'bg-gray-100 text-gray-700',
};
</script>

<template>
    <Head title="Payments" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Payments Ledger</h1>
        </template>

        <!-- Toolbar -->
        <div class="flex items-center justify-between mb-5">
            <p class="text-sm text-gray-500">
                <span class="font-semibold text-gray-700">{{ payments.total }}</span> transaction(s)
            </p>
            <Link :href="route('credits.index')"
                class="inline-flex items-center gap-1 text-sm text-orange-600 hover:text-orange-700 font-medium hover:underline">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
                Credit Accounts
            </Link>
        </div>

        <!-- Table -->
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Client Name</th>
                            <th>Receipt No</th>
                            <th>Chq / Ref</th>
                            <th>Trans Type</th>
                            <th>Method</th>
                            <th class="text-right">Amount</th>
                            <th class="w-24"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in payments.data" :key="p.id">
                            <td class="text-gray-600 whitespace-nowrap">{{ fmtDate(p.payment_date) }}</td>
                            <td class="font-medium text-gray-800">
                                <Link :href="route('credits.show', p.credit_customer_id)"
                                    class="hover:text-orange-600 hover:underline transition-colors">
                                    {{ p.customer_name }}
                                </Link>
                            </td>
                            <td class="font-mono text-gray-600 text-xs">{{ p.receipt_no ?? '—' }}</td>
                            <td class="font-mono text-gray-500 text-xs">{{ p.reference ?? '—' }}</td>
                            <td>
                                <span class="badge"
                                    :class="transTypeColor[p.trans_type] ?? 'bg-gray-100 text-gray-600'">
                                    {{ transTypeLabel[p.trans_type] ?? p.trans_type }}
                                </span>
                            </td>
                            <td class="text-gray-500 text-xs">
                                {{ methodLabel[p.payment_method] ?? p.payment_method }}
                            </td>
                            <td class="text-right font-semibold tabular-nums"
                                :class="p.trans_type === 'receipts' ? 'text-green-700' : 'text-red-600'">
                                {{ fmt(p.amount) }}
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <button @click="openEdit(p)"
                                    class="text-xs text-orange-600 hover:text-orange-700 hover:underline mr-3 font-medium transition-colors">
                                    Edit
                                </button>
                                <button @click="deletePayment(p)"
                                    class="text-xs text-red-500 hover:text-red-600 hover:underline font-medium transition-colors">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!payments.data?.length">
                            <td colspan="8">
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                                        </svg>
                                    </div>
                                    <p>No payments recorded.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                    <!-- Footer totals -->
                    <tfoot v-if="payments.data?.length" class="bg-gray-50/80 border-t-2 border-gray-200">
                        <tr>
                            <td colspan="6" class="px-4 py-3 text-sm font-semibold text-gray-600">
                                Total Receipts
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-green-700 tabular-nums">
                                {{ fmt(payments.data.filter(p => p.trans_type === 'receipts').reduce((s, p) => s + Number(p.amount), 0)) }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="payments.last_page > 1" class="px-4 py-3 border-t border-gray-100 flex justify-between items-center text-sm bg-gray-50/50">
                <span class="text-gray-500">Page <span class="font-medium text-gray-700">{{ payments.current_page }}</span> of {{ payments.last_page }}</span>
                <div class="flex gap-1.5">
                    <Link v-if="payments.prev_page_url" :href="payments.prev_page_url"
                        class="inline-flex items-center gap-1 px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-white hover:border-gray-300 transition-colors text-gray-600 font-medium">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                        </svg>
                        Prev
                    </Link>
                    <Link v-if="payments.next_page_url" :href="payments.next_page_url"
                        class="inline-flex items-center gap-1 px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-white hover:border-gray-300 transition-colors text-gray-600 font-medium">
                        Next
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </Link>
                </div>
            </div>
        </div>

        <!-- ── Edit modal ── -->
        <Transition
            enter-active-class="transition-all duration-200"
            leave-active-class="transition-all duration-150"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0">
            <div v-if="editTarget"
                class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50 p-4"
                @click.self="editTarget = null">
                <Transition
                    enter-active-class="transition-all duration-200"
                    enter-from-class="opacity-0 scale-95 translate-y-4"
                    appear>
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg animate-scale-in">
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                            <div>
                                <h2 class="font-semibold text-gray-800">Edit Payment</h2>
                                <p class="text-xs text-gray-500 mt-0.5">{{ editTarget.customer_name }}</p>
                            </div>
                            <button @click="editTarget = null" aria-label="Close dialog"
                                class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <form @submit.prevent="submitEdit" class="p-6 space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="form-field">
                                    <label>Date</label>
                                    <input type="date" v-model="editForm.payment_date" required />
                                </div>
                                <div class="form-field">
                                    <label>Receipt / Invoice No</label>
                                    <input type="text" v-model="editForm.receipt_no" class="font-mono" />
                                </div>
                                <div class="form-field">
                                    <label>Trans Type</label>
                                    <select v-model="editForm.trans_type">
                                        <option value="receipts">Receipts</option>
                                        <option value="fuel">Fuel</option>
                                        <option value="lpg">LPG</option>
                                        <option value="pos">POS</option>
                                        <option value="invoice">Invoice</option>
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label>Chq No / Reference</label>
                                    <input type="text" v-model="editForm.reference" class="font-mono" />
                                </div>
                                <div class="form-field">
                                    <label>Payment Method</label>
                                    <select v-model="editForm.payment_method">
                                        <option value="cash">Cash</option>
                                        <option value="mpesa">M-Pesa</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="cheque">Cheque</option>
                                        <option value="rtgs">RTGS</option>
                                        <option value="equity_card">Equity Card</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label>Amount (KES)</label>
                                    <input type="number" v-model="editForm.amount" step="0.01" required min="0.01" />
                                </div>
                            </div>
                            <div class="form-field">
                                <label>Notes</label>
                                <input type="text" v-model="editForm.notes" />
                            </div>
                            <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                                <button type="button" @click="editTarget = null"
                                    class="px-4 py-2 border border-gray-200 rounded-lg text-sm hover:bg-gray-50 transition-colors font-medium">
                                    Cancel
                                </button>
                                <button type="submit" :disabled="editForm.processing"
                                    class="bg-orange-500 text-white px-6 py-2 rounded-lg text-sm hover:bg-orange-600 disabled:opacity-60 font-medium transition-colors shadow-sm shadow-orange-500/20">
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </Transition>
            </div>
        </Transition>
    </AuthenticatedLayout>

    <ConfirmModal
        :show="confirmModal.show"
        :title="confirmModal.title"
        :message="confirmModal.message"
        :variant="confirmModal.variant"
        @confirm="handleConfirm"
        @cancel="closeConfirm" />
</template>
