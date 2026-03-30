<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { fmt, fmtDate } from '@/composables/useFormatters';

const props = defineProps({
    transactions: Object,
});

// ── Confirm modal ─────────────────────────────────────────────
const confirmModal = ref({ show: false, title: '', message: '', variant: 'danger', onConfirm: () => {} });
function openConfirm({ title, message, variant = 'danger', onConfirm }) {
    confirmModal.value = { show: true, title, message, variant, onConfirm };
}
function closeConfirm() { confirmModal.value.show = false; }
function handleConfirm() { confirmModal.value.onConfirm(); closeConfirm(); }

// ── Edit modal ─────────────────────────────────────────────────
const editTarget = ref(null);

const editForm = useForm({
    trans_date: '',
    reference:  '',
    amount:     '',
});

function openEdit(t) {
    editTarget.value = t;
    editForm.trans_date = t.trans_date?.slice(0, 10) ?? '';
    editForm.reference  = t.reference ?? '';
    editForm.amount     = t.amount;
}

function submitEdit() {
    editForm.put(route('pos-transactions.update', editTarget.value.id), {
        onSuccess: () => { editTarget.value = null; },
    });
}

function deleteTransaction(t) {
    openConfirm({
        title: 'Delete POS Transaction',
        message: `Delete POS transaction ${t.reference} — KES ${fmt(t.amount)}?`,
        onConfirm: () => {
            router.delete(route('pos-transactions.destroy', t.id), { preserveScroll: true });
        },
    });
}

// ── Totals ─────────────────────────────────────────────────────
const grandTotal = computed(() =>
    props.transactions.data.reduce((s, t) => s + Number(t.amount), 0)
);


</script>

<template>
    <Head title="POS Account" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">POS Account</h1>
        </template>

        <!-- Summary strip -->
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-5">
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
                <p class="text-xs text-gray-400">Total Transactions</p>
                <p class="text-2xl font-bold text-gray-800">{{ transactions.total }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-orange-500">
                <p class="text-xs text-gray-400">Total Amount</p>
                <p class="text-2xl font-bold text-gray-800">KES {{ fmt(grandTotal) }}</p>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-gray-500 font-medium">Date</th>
                            <th class="px-4 py-3 text-left text-gray-500 font-medium">Ref</th>
                            <th class="px-4 py-3 text-right text-gray-500 font-medium">Amount</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in transactions.data" :key="t.id"
                            class="border-t border-gray-100 hover:bg-gray-50">
                            <td class="px-4 py-2.5 text-gray-600 whitespace-nowrap">{{ fmtDate(t.trans_date) }}</td>
                            <td class="px-4 py-2.5 font-mono text-gray-800">{{ t.reference }}</td>
                            <td class="px-4 py-2.5 text-right font-semibold text-gray-800">{{ fmt(t.amount) }}</td>
                            <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                <button @click="openEdit(t)"
                                    class="text-xs text-orange-600 hover:underline mr-3">
                                    Edit
                                </button>
                                <button @click="deleteTransaction(t)"
                                    class="text-xs text-red-500 hover:underline">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!transactions.data?.length">
                            <td colspan="4" class="px-4 py-10 text-center text-gray-400">
                                No POS transactions recorded.
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="transactions.data?.length" class="bg-gray-50 border-t-2 border-gray-300">
                        <tr>
                            <td colspan="2" class="px-4 py-3 font-semibold text-gray-600">Total</td>
                            <td class="px-4 py-3 text-right font-bold text-gray-800">{{ fmt(grandTotal) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="transactions.last_page > 1" class="px-4 py-3 border-t flex justify-between items-center text-sm">
                <span class="text-gray-500">Page {{ transactions.current_page }} of {{ transactions.last_page }}</span>
                <div class="flex gap-2">
                    <Link v-if="transactions.prev_page_url" :href="transactions.prev_page_url"
                        class="px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50">Prev</Link>
                    <Link v-if="transactions.next_page_url" :href="transactions.next_page_url"
                        class="px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50">Next</Link>
                </div>
            </div>
        </div>

        <!-- Edit modal -->
        <div v-if="editTarget"
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h2 class="font-semibold text-gray-800">Edit POS Transaction</h2>
                    <button @click="editTarget = null" aria-label="Close dialog" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <form @submit.prevent="submitEdit" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Date</label>
                        <input type="date" v-model="editForm.trans_date" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Ref</label>
                        <input type="text" v-model="editForm.reference" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Amount (KES)</label>
                        <input type="number" v-model="editForm.amount" step="0.01" required min="0.01"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                        <button type="button" @click="editTarget = null"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-sm">
                            Cancel
                        </button>
                        <button type="submit" :disabled="editForm.processing"
                            class="bg-orange-500 text-white px-6 py-2 rounded-lg text-sm hover:bg-orange-600 disabled:opacity-60">
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
