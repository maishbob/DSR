<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { fmt, fmtDate } from '@/composables/useFormatters';

const props = defineProps({
    recons: Object,
});

// ── Confirm modal ─────────────────────────────────────────────
const confirmModal = ref({ show: false, title: '', message: '', variant: 'danger', onConfirm: () => {} });
function openConfirm({ title, message, variant = 'danger', onConfirm }) {
    confirmModal.value = { show: true, title, message, variant, onConfirm };
}
function closeConfirm() { confirmModal.value.show = false; }
function handleConfirm() { confirmModal.value.onConfirm(); closeConfirm(); }

// ── Selected recon (detail panel) ────────────────────────────
const selected = ref(null);

function select(r) {
    selected.value = r;
    // Load full lines from the recon object (already loaded via withSum)
    // If lines aren't embedded, we need to navigate or load via separate route.
    // We'll open edit modal with lines embedded — reload page to get lines.
    openEdit(r);
}

// ── New recon form ────────────────────────────────────────────
const showNew = ref(false);

const newForm = useForm({
    card_name:  '',
    batch_ref:  '',
    recon_date: new Date().toISOString().slice(0, 10),
});

function submitNew() {
    newForm.post(route('card-recons.store'), {
        onSuccess: () => { showNew.value = false; newForm.reset(); },
    });
}

// ── Edit / detail modal ───────────────────────────────────────
const editTarget = ref(null);
const editLines  = ref([]);

const editForm = useForm({
    card_name:  '',
    batch_ref:  '',
    recon_date: '',
});

const lineForm = useForm({
    trans_date: new Date().toISOString().slice(0, 10),
    ref:        '',
    amount:     '',
});

function openEdit(r) {
    editTarget.value = r;
    editLines.value  = r.lines ?? [];
    editForm.card_name  = r.card_name;
    editForm.batch_ref  = r.batch_ref ?? '';
    editForm.recon_date = r.recon_date?.slice(0, 10) ?? '';
    lineForm.reset();
    lineForm.trans_date = r.recon_date?.slice(0, 10) ?? new Date().toISOString().slice(0, 10);
}

function submitEdit() {
    editForm.put(route('card-recons.update', editTarget.value.id), {
        onSuccess: () => { editTarget.value = null; },
    });
}

function deleteRecon(r) {
    openConfirm({
        title: 'Delete Reconciliation',
        message: `Delete recon ${r.card_name} – ${r.batch_ref ?? r.id}?`,
        onConfirm: () => {
            router.delete(route('card-recons.destroy', r.id), { preserveScroll: true });
        },
    });
}

function submitLine() {
    lineForm.post(route('card-recon-lines.store', editTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => { lineForm.ref = ''; lineForm.amount = ''; },
    });
}

function deleteLine(line) {
    router.delete(route('card-recon-lines.destroy', line.id), { preserveScroll: true });
}

// ── Computed totals ───────────────────────────────────────────
const linesTotal = computed(() =>
    editLines.value.reduce((s, l) => s + Number(l.amount), 0)
);

// ── Card name options ─────────────────────────────────────────
const cardOptions = ['EQUITY', 'BARCLAYS', 'KCB', 'NCBA', 'COOPERATIVE', 'ABSA', 'STANBIC', 'OTHER'];



const cardColor = {
    EQUITY:      'bg-green-100 text-green-800',
    BARCLAYS:    'bg-blue-100 text-blue-800',
    KCB:         'bg-red-100 text-red-800',
    NCBA:        'bg-purple-100 text-purple-800',
    COOPERATIVE: 'bg-teal-100 text-teal-800',
    ABSA:        'bg-orange-100 text-orange-800',
};
</script>

<template>
    <Head title="Credit Card Recons" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Credit Card Recons</h1>
        </template>

        <!-- Toolbar -->
        <div class="flex items-center justify-between mb-5">
            <p class="text-sm text-gray-500">{{ recons.total }} recon batch(es)</p>
            <button @click="showNew = !showNew"
                class="bg-orange-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-orange-600 font-medium">
                + New Recon Batch
            </button>
        </div>

        <!-- New recon form -->
        <div v-if="showNew" class="bg-white rounded-xl shadow-sm p-5 mb-5">
            <h2 class="font-semibold text-gray-700 mb-4">New Recon Batch</h2>
            <form @submit.prevent="submitNew" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Card Name *</label>
                    <input list="card-options-new" v-model="newForm.card_name" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. EQUITY" />
                    <datalist id="card-options-new">
                        <option v-for="c in cardOptions" :key="c" :value="c" />
                    </datalist>
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Batch Ref</label>
                    <input type="text" v-model="newForm.batch_ref"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono"
                        placeholder="e.g. 22190101" />
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Recon Date *</label>
                    <input type="date" v-model="newForm.recon_date" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                </div>
                <div class="flex gap-2">
                    <button type="submit" :disabled="newForm.processing"
                        class="flex-1 bg-orange-500 text-white py-2 rounded-lg text-sm hover:bg-orange-600 disabled:opacity-60">
                        Create
                    </button>
                    <button type="button" @click="showNew = false"
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>

        <!-- Recons table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-gray-500 font-medium">Card Name</th>
                            <th class="px-4 py-3 text-left text-gray-500 font-medium">Batch Ref</th>
                            <th class="px-4 py-3 text-right text-gray-500 font-medium">Recon ID</th>
                            <th class="px-4 py-3 text-left text-gray-500 font-medium">Recon Date</th>
                            <th class="px-4 py-3 text-right text-gray-500 font-medium">Total Amount</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in recons.data" :key="r.id"
                            class="border-t border-gray-100 hover:bg-orange-50 cursor-pointer"
                            @click="openEdit(r)">
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 text-xs rounded-full font-semibold"
                                    :class="cardColor[r.card_name] ?? 'bg-gray-100 text-gray-700'">
                                    {{ r.card_name }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-mono text-gray-700">{{ r.batch_ref ?? '—' }}</td>
                            <td class="px-4 py-3 text-right text-gray-500 font-mono">{{ r.id.toLocaleString() }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ fmtDate(r.recon_date) }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-gray-800">
                                {{ fmt(r.total_amount) }}
                            </td>
                            <td class="px-4 py-3 text-right" @click.stop>
                                <button @click="deleteRecon(r)"
                                    class="text-xs text-red-500 hover:underline">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!recons.data?.length">
                            <td colspan="6" class="px-4 py-10 text-center text-gray-400">
                                No recon batches recorded.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="recons.last_page > 1" class="px-4 py-3 border-t flex justify-between items-center text-sm">
                <span class="text-gray-500">Page {{ recons.current_page }} of {{ recons.last_page }}</span>
                <div class="flex gap-2">
                    <Link v-if="recons.prev_page_url" :href="recons.prev_page_url"
                        class="px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50">Prev</Link>
                    <Link v-if="recons.next_page_url" :href="recons.next_page_url"
                        class="px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50">Next</Link>
                </div>
            </div>
        </div>

        <!-- ── Detail / Edit modal ── -->
        <div v-if="editTarget"
            class="fixed inset-0 bg-black/50 flex items-start justify-center z-50 p-4 overflow-y-auto">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl my-8">
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h2 class="font-semibold text-gray-800">
                        Recon Batch
                        <span class="text-gray-400 font-normal ml-1">#{{ editTarget.id }}</span>
                    </h2>
                    <button @click="editTarget = null" aria-label="Close dialog" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Batch details form -->
                <form @submit.prevent="submitEdit" class="px-6 pt-5 pb-4 border-b border-gray-100">
                    <div class="grid grid-cols-3 gap-4 items-end">
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Card Name</label>
                            <input list="card-options-edit" v-model="editForm.card_name" required
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                            <datalist id="card-options-edit">
                                <option v-for="c in cardOptions" :key="c" :value="c" />
                            </datalist>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Batch Ref</label>
                            <input type="text" v-model="editForm.batch_ref"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Recon Date</label>
                            <input type="date" v-model="editForm.recon_date" required
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                        </div>
                    </div>
                    <div class="flex justify-end mt-3">
                        <button type="submit" :disabled="editForm.processing"
                            class="bg-orange-500 text-white px-5 py-1.5 rounded-lg text-sm hover:bg-orange-600 disabled:opacity-60">
                            Save Header
                        </button>
                    </div>
                </form>

                <!-- Transaction lines -->
                <div class="px-6 py-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Transactions</h3>
                    <table class="w-full text-sm mb-3">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-3 py-2 text-left text-gray-500 font-medium">Trans Date</th>
                                <th class="px-3 py-2 text-left text-gray-500 font-medium">Ref</th>
                                <th class="px-3 py-2 text-right text-gray-500 font-medium">Amount</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="line in editLines" :key="line.id"
                                class="border-t border-gray-100 hover:bg-gray-50">
                                <td class="px-3 py-2 text-gray-600">{{ fmtDate(line.trans_date) }}</td>
                                <td class="px-3 py-2 font-mono text-gray-700">{{ line.ref ?? '—' }}</td>
                                <td class="px-3 py-2 text-right font-medium text-gray-800">{{ fmt(line.amount) }}</td>
                                <td class="px-3 py-2 text-right">
                                    <button @click="deleteLine(line)"
                                        aria-label="Delete recon line"
                                        class="p-1 rounded text-red-400 hover:text-red-600 hover:bg-red-50 transition-colors">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!editLines.length">
                                <td colspan="4" class="px-3 py-4 text-center text-gray-400 text-xs">
                                    No transactions yet.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t-2 border-gray-300 bg-gray-50">
                            <tr>
                                <td colspan="2" class="px-3 py-2 text-sm font-semibold text-gray-600">
                                    Total Amount:
                                </td>
                                <td class="px-3 py-2 text-right font-bold text-gray-800 text-base">
                                    {{ fmt(linesTotal) }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>

                    <!-- Add line form -->
                    <form @submit.prevent="submitLine"
                        class="grid grid-cols-3 gap-3 items-end bg-gray-50 rounded-lg p-3">
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Trans Date</label>
                            <input type="date" v-model="lineForm.trans_date" required
                                class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Ref</label>
                            <input type="text" v-model="lineForm.ref"
                                class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm font-mono"
                                placeholder="e.g. 26010111720" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Amount</label>
                            <div class="flex gap-2">
                                <input type="number" v-model="lineForm.amount" step="0.01" required min="0.01"
                                    class="flex-1 border border-gray-300 rounded-lg px-2 py-1.5 text-sm" />
                                <button type="submit" :disabled="lineForm.processing"
                                    class="bg-orange-500 text-white px-3 py-1.5 rounded-lg text-sm hover:bg-orange-600 disabled:opacity-60">
                                    + Add
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex justify-end">
                    <button @click="editTarget = null"
                        class="px-5 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
