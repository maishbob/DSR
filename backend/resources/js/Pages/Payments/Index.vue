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
            <p class="text-sm text-gray-500">{{ payments.total }} transaction(s)</p>
            <Link :href="route('credits.index')"
                class="text-sm text-orange-600 hover:underline">
                ← Credit Accounts
            </Link>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-gray-500 font-medium">Date</th>
                            <th class="px-4 py-3 text-left text-gray-500 font-medium">Client Name</th>
                            <th class="px-4 py-3 text-left text-gray-500 font-medium">Receipt No</th>
                            <th class="px-4 py-3 text-left text-gray-500 font-medium">Chq / Ref</th>
                            <th class="px-4 py-3 text-left text-gray-500 font-medium">Trans Type</th>
                            <th class="px-4 py-3 text-left text-gray-500 font-medium">Method</th>
                            <th class="px-4 py-3 text-right text-gray-500 font-medium">Amount</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in payments.data" :key="p.id"
                            class="border-t border-gray-100 hover:bg-gray-50">
                            <td class="px-4 py-2.5 text-gray-600 whitespace-nowrap">{{ fmtDate(p.payment_date) }}</td>
                            <td class="px-4 py-2.5 font-medium text-gray-800">
                                <Link :href="route('credits.show', p.credit_customer_id)"
                                    class="hover:text-orange-600 hover:underline">
                                    {{ p.customer_name }}
                                </Link>
                            </td>
                            <td class="px-4 py-2.5 font-mono text-gray-600">{{ p.receipt_no ?? '—' }}</td>
                            <td class="px-4 py-2.5 font-mono text-gray-500">{{ p.reference ?? '—' }}</td>
                            <td class="px-4 py-2.5">
                                <span class="px-2 py-0.5 text-xs rounded-full font-medium"
                                    :class="transTypeColor[p.trans_type] ?? 'bg-gray-100 text-gray-600'">
                                    {{ transTypeLabel[p.trans_type] ?? p.trans_type }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-gray-500 text-xs">
                                {{ methodLabel[p.payment_method] ?? p.payment_method }}
                            </td>
                            <td class="px-4 py-2.5 text-right font-semibold"
                                :class="p.trans_type === 'receipts' ? 'text-green-700' : 'text-red-600'">
                                {{ fmt(p.amount) }}
                            </td>
                            <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                <button @click="openEdit(p)"
                                    class="text-xs text-orange-600 hover:underline mr-3">
                                    Edit
                                </button>
                                <button @click="deletePayment(p)"
                                    class="text-xs text-red-500 hover:underline">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!payments.data?.length">
                            <td colspan="8" class="px-4 py-10 text-center text-gray-400">
                                No payments recorded.
                            </td>
                        </tr>
                    </tbody>
                    <!-- Footer totals -->
                    <tfoot v-if="payments.data?.length" class="bg-gray-50 border-t-2 border-gray-300">
                        <tr>
                            <td colspan="6" class="px-4 py-3 text-sm font-semibold text-gray-600">
                                Total Receipts
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-green-700">
                                {{ fmt(payments.data.filter(p => p.trans_type === 'receipts').reduce((s, p) => s + Number(p.amount), 0)) }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="payments.last_page > 1" class="px-4 py-3 border-t flex justify-between items-center text-sm">
                <span class="text-gray-500">Page {{ payments.current_page }} of {{ payments.last_page }}</span>
                <div class="flex gap-2">
                    <Link v-if="payments.prev_page_url" :href="payments.prev_page_url"
                        class="px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50">Prev</Link>
                    <Link v-if="payments.next_page_url" :href="payments.next_page_url"
                        class="px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50">Next</Link>
                </div>
            </div>
        </div>

        <!-- ── Edit modal ── -->
        <div v-if="editTarget"
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <div>
                        <h2 class="font-semibold text-gray-800">Edit Payment</h2>
                        <p class="text-xs text-gray-500 mt-0.5">{{ editTarget.customer_name }}</p>
                    </div>
                    <button @click="editTarget = null" aria-label="Close dialog" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <form @submit.prevent="submitEdit" class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Date</label>
                            <input type="date" v-model="editForm.payment_date" required
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Receipt / Invoice No</label>
                            <input type="text" v-model="editForm.receipt_no"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Trans Type</label>
                            <select v-model="editForm.trans_type"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                <option value="receipts">Receipts</option>
                                <option value="fuel">Fuel</option>
                                <option value="lpg">LPG</option>
                                <option value="pos">POS</option>
                                <option value="invoice">Invoice</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Chq No / Reference</label>
                            <input type="text" v-model="editForm.reference"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Payment Method</label>
                            <select v-model="editForm.payment_method"
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
                            <label class="block text-xs text-gray-600 mb-1">Amount (KES)</label>
                            <input type="number" v-model="editForm.amount" step="0.01" required min="0.01"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Notes</label>
                        <input type="text" v-model="editForm.notes"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                        <button type="button" @click="editTarget = null"
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

    <ConfirmModal
        :show="confirmModal.show"
        :title="confirmModal.title"
        :message="confirmModal.message"
        :variant="confirmModal.variant"
        @confirm="handleConfirm"
        @cancel="closeConfirm" />
</template>
