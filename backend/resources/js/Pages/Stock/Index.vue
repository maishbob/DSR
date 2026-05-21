<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { fmt, fmtDate } from '@/composables/useFormatters';
const props = defineProps({
    products: Object,
    filters:  Object,
});

// ── Search & per-page ─────────────────────────────────────────
const search  = ref(props.filters?.search   ?? '');
const perPage = ref(props.filters?.per_page ?? 20);

function applyFilters() {
    const params = {};
    if (search.value) params.search = search.value;
    if (perPage.value !== 20) params.per_page = perPage.value;
    router.get(route('stock.index'), params, { preserveState: true, replace: true });
}

let searchTimer = null;
watch(search, (val) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 350);
});
watch(perPage, applyFilters);

// ── Expanded row + lazy oil-sales fetch ───────────────────────
const expandedId     = ref(null);
const oilSalesCache  = ref({});   // { [productId]: OilSale[] }
const oilSalesLoading = ref({});  // { [productId]: true }

async function toggleExpand(id) {
    if (expandedId.value === id) { expandedId.value = null; return; }
    expandedId.value = id;
    if (id in oilSalesCache.value) return;

    oilSalesLoading.value = { ...oilSalesLoading.value, [id]: true };
    try {
        const res  = await fetch(route('stock.oil-sales', id));
        const data = await res.json();
        oilSalesCache.value = { ...oilSalesCache.value, [id]: data };
    } catch {
        oilSalesCache.value = { ...oilSalesCache.value, [id]: [] };
    } finally {
        const copy = { ...oilSalesLoading.value };
        delete copy[id];
        oilSalesLoading.value = copy;
    }
}

function combinedHistory(product) {
    const txs = (product.stock_transactions ?? []).map(t => ({
        key:       'tx-' + t.id,
        date:      t.trans_date,
        type:      t.type,
        rawQty:    Number(t.quantity),
        ref:       t.document_ref ?? null,
        notes:     t.notes ?? null,
        by:        t.entered_by?.name ?? null,
        deletable: t.type !== 'iss',
        txId:      t.id,
        fromDsr:   false,
    }));

    const sales = (oilSalesCache.value[product.id] ?? []).map(s => ({
        key:       'os-' + s.id,
        date:      s.shift?.shift_date ?? null,
        type:      'iss',
        rawQty:    Number(s.quantity),
        ref:       s.shift?.dsr_number != null ? 'DSR #' + s.shift.dsr_number : null,
        dsrNumber: s.shift?.dsr_number ?? null,
        notes:     null,
        by:        s.entered_by?.name ?? null,
        deletable: false,
        txId:      null,
        fromDsr:   true,
    }));

    return [...txs, ...sales].sort((a, b) =>
        (b.date ?? '').localeCompare(a.date ?? '')
    );
}

// ── Confirm modal ─────────────────────────────────────────────
const confirmModal = ref({ show: false, title: '', message: '', onConfirm: () => {} });
function openConfirm({ title, message, onConfirm }) {
    confirmModal.value = { show: true, title, message, variant: 'danger', onConfirm };
}
function closeConfirm() { confirmModal.value.show = false; }
function handleConfirm() { confirmModal.value.onConfirm(); closeConfirm(); }

// ── GRN / Adj modal ───────────────────────────────────────────
const modalType    = ref(null); // 'grn' | 'adj'
const modalProduct = ref(null);

const txForm = useForm({
    type:         '',
    trans_date:   new Date().toISOString().slice(0, 10),
    quantity:     '',
    target:       'forecourt',
    document_ref: '',
    notes:        '',
});

function openGrn(product) {
    modalType.value    = 'grn';
    modalProduct.value = product;
    txForm.reset();
    txForm.type       = 'grn';
    txForm.trans_date = new Date().toISOString().slice(0, 10);
    txForm.target     = 'forecourt';
}

function openAdj(product) {
    modalType.value    = 'adj';
    modalProduct.value = product;
    txForm.reset();
    txForm.type       = 'adj';
    txForm.trans_date = new Date().toISOString().slice(0, 10);
    txForm.target     = 'forecourt';
}

function closeModal() {
    modalType.value    = null;
    modalProduct.value = null;
    txForm.reset();
}

function submitTx() {
    txForm.post(route('stock-transactions.store', modalProduct.value.id), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
}

function deleteTx(txId) {
    openConfirm({
        title:   'Delete Transaction',
        message: 'Reverse and delete this stock transaction?',
        onConfirm: () => router.delete(route('stock-transactions.destroy', txId), { preserveScroll: true }),
    });
}

// ── Summary stats ─────────────────────────────────────────────
const totalProducts = computed(() => props.products.total);
const lowStockCount = computed(() => (props.products.data ?? []).filter(p => p.is_active && p.current_stock < 5).length);

const txTypeLabel = { grn: 'GRN', adj: 'Adj', iss: 'Sale' };
const txTypeClass = {
    grn: 'bg-green-100 text-green-700',
    adj: 'bg-yellow-100 text-yellow-700',
    iss: 'bg-red-100 text-red-700',
};

const pageLinks = computed(() =>
    (props.products?.links ?? []).slice(1, -1)
);
</script>

<template>
    <Head title="Stock Management" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Stock Management</h1>
        </template>

        <!-- Summary bar -->
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-5">
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Products</p>
                <p class="text-2xl font-bold text-gray-800">{{ totalProducts }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Low Stock</p>
                <p class="text-2xl font-bold" :class="lowStockCount > 0 ? 'text-red-600' : 'text-gray-400'">
                    {{ lowStockCount }}
                </p>
            </div>
        </div>

        <!-- DataTables-style container -->
        <div class="bg-white border border-gray-300 rounded text-sm">

            <!-- Top bar: Show entries + Search -->
            <div class="flex flex-wrap justify-between items-center gap-3 px-4 py-3 border-b border-gray-200 bg-gray-50">
                <div class="flex items-center gap-1.5 text-gray-600">
                    <span>Show</span>
                    <select v-model="perPage"
                        class="border border-gray-300 rounded px-1.5 py-0.5 text-sm bg-white focus:outline-none focus:ring-1 focus:ring-blue-400">
                        <option :value="10">10</option>
                        <option :value="20">20</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                    <span>entries</span>
                </div>
                <div class="flex items-center gap-1.5 text-gray-600">
                    <span>Search:</span>
                    <input v-model="search" type="text"
                        class="border border-gray-300 rounded px-2 py-0.5 text-sm w-44 focus:outline-none focus:ring-1 focus:ring-blue-400" />
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-gray-100 border-b-2 border-gray-300 text-left text-gray-700 font-semibold">
                            <th class="px-3 py-2.5 border-r border-gray-200 w-6"></th>
                            <th class="px-3 py-2.5 border-r border-gray-200">Product</th>
                            <th class="px-3 py-2.5 border-r border-gray-200">Unit</th>
                            <th class="px-3 py-2.5 text-right border-r border-gray-200">Forecourt</th>
                            <th class="px-3 py-2.5 text-right border-r border-gray-200">Store</th>
                            <th class="px-3 py-2.5 text-right border-r border-gray-200">Total</th>
                            <th class="px-3 py-2.5 text-right border-r border-gray-200">Price</th>
                            <th class="px-3 py-2.5 text-right border-r border-gray-200">Cost</th>
                            <th class="px-3 py-2.5 border-r border-gray-200">Status</th>
                            <th class="px-3 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(p, i) in products.data" :key="p.id">
                            <!-- Main row -->
                            <tr class="border-b border-gray-200 cursor-pointer hover:bg-blue-50"
                                :class="i % 2 === 1 ? 'bg-gray-50' : 'bg-white'"
                                @click="toggleExpand(p.id)">
                                <td class="px-3 py-2 text-gray-400 border-r border-gray-100">
                                    <svg class="w-4 h-4 transition-transform"
                                        :class="expandedId === p.id ? 'rotate-90' : ''"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                    </svg>
                                </td>
                                <td class="px-3 py-2 font-medium text-gray-800 border-r border-gray-100">
                                    {{ p.product_name }}
                                    <span v-if="p.is_active && p.current_stock < 5"
                                        class="ml-2 px-1.5 py-0.5 text-xs bg-red-100 text-red-600 rounded-full">Low</span>
                                </td>
                                <td class="px-3 py-2 text-gray-500 border-r border-gray-100">{{ p.unit ?? '—' }}</td>
                                <td class="px-3 py-2 text-right font-mono border-r border-gray-100">{{ fmt(p.forecourt_stock, 0) }}</td>
                                <td class="px-3 py-2 text-right font-mono text-gray-500 border-r border-gray-100">{{ fmt(p.store_stock, 0) }}</td>
                                <td class="px-3 py-2 text-right font-mono font-semibold border-r border-gray-100"
                                    :class="p.current_stock < 5 ? 'text-red-600' : 'text-gray-800'">
                                    {{ fmt(p.current_stock, 0) }}
                                </td>
                                <td class="px-3 py-2 text-right font-mono border-r border-gray-100">{{ p.current_price ? fmt(p.current_price) : '—' }}</td>
                                <td class="px-3 py-2 text-right font-mono text-gray-500 border-r border-gray-100">{{ p.cost ? fmt(p.cost) : '—' }}</td>
                                <td class="px-3 py-2 border-r border-gray-100">
                                    <span class="px-2 py-0.5 text-xs rounded"
                                        :class="p.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500'">
                                        {{ p.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2" @click.stop>
                                    <div class="flex gap-2 justify-end">
                                        <button @click="openGrn(p)"
                                            class="px-2.5 py-1 text-xs font-medium bg-orange-500 text-white rounded hover:bg-orange-600">
                                            GRN
                                        </button>
                                        <button @click="openAdj(p)"
                                            class="px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                                            Adj
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Expanded: transaction history -->
                            <tr v-if="expandedId === p.id" class="bg-gray-50 border-t border-gray-100">
                                <td colspan="10" class="px-8 py-4">
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Transaction History</p>

                                    <!-- Loading spinner -->
                                    <div v-if="oilSalesLoading[p.id]" class="py-4 text-center text-gray-400 text-xs">
                                        Loading sales history…
                                    </div>

                                    <table v-else class="w-full text-xs">
                                        <thead>
                                            <tr class="text-gray-400 border-b border-gray-200">
                                                <th class="pb-1 text-left font-medium">Date</th>
                                                <th class="pb-1 text-left font-medium">Type</th>
                                                <th class="pb-1 pr-4 text-right font-medium">Qty</th>
                                                <th class="pb-1 text-left font-medium">Ref</th>
                                                <th class="pb-1 text-left font-medium">Notes</th>
                                                <th class="pb-1 text-left font-medium">By</th>
                                                <th class="pb-1"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <tr v-for="entry in combinedHistory(p)" :key="entry.key" class="hover:bg-white">
                                                <td class="py-1.5 text-gray-600">{{ fmtDate(entry.date) }}</td>
                                                <td class="py-1.5">
                                                    <span class="px-1.5 py-0.5 rounded text-xs font-medium"
                                                        :class="txTypeClass[entry.type] ?? 'bg-gray-100 text-gray-500'">
                                                        {{ txTypeLabel[entry.type] ?? entry.type }}
                                                    </span>
                                                    <span v-if="entry.fromDsr" class="ml-1 text-gray-400">(DSR)</span>
                                                </td>
                                                <td class="py-1.5 pr-4 text-right font-mono"
                                                    :class="(entry.type === 'iss' || entry.rawQty < 0) ? 'text-red-600' : 'text-gray-800'">
                                                    <template v-if="entry.type === 'iss'">-{{ fmt(entry.rawQty, 0) }}</template>
                                                    <template v-else-if="entry.rawQty >= 0">+{{ fmt(entry.rawQty, 0) }}</template>
                                                    <template v-else>{{ fmt(entry.rawQty, 0) }}</template>
                                                </td>
                                                <td class="py-1.5 font-mono">
                                                    <a v-if="entry.dsrNumber != null"
                                                        :href="route('dsr.view-by-number', entry.dsrNumber)"
                                                        class="text-blue-600 hover:underline"
                                                        @click.stop>
                                                        {{ entry.ref }}
                                                    </a>
                                                    <span v-else class="text-gray-500">{{ entry.ref ?? '—' }}</span>
                                                </td>
                                                <td class="py-1.5 text-gray-500">{{ entry.notes ?? '—' }}</td>
                                                <td class="py-1.5 text-gray-400">{{ entry.by ?? '—' }}</td>
                                                <td class="py-1.5 text-right">
                                                    <button v-if="entry.deletable"
                                                        @click="deleteTx(entry.txId)"
                                                        class="p-0.5 text-red-400 hover:text-red-600 rounded">
                                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr v-if="!combinedHistory(p).length">
                                                <td colspan="7" class="py-3 text-center text-gray-400">No transactions recorded.</td>
                                            </tr>
                                        </tbody>
                                        <tfoot v-if="combinedHistory(p).length" class="border-t-2 border-gray-300 bg-gray-100">
                                            <tr>
                                                <td colspan="2" class="py-1.5 px-0 text-xs font-semibold text-gray-600">Total Sales</td>
                                                <td class="py-1.5 pr-4 text-right font-mono font-semibold text-red-600">
                                                    -{{ fmt(combinedHistory(p).filter(e => e.type === 'iss').reduce((s, e) => s + e.rawQty, 0), 0) }}
                                                </td>
                                                <td colspan="4"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </td>
                            </tr>
                        </template>

                        <tr v-if="!products.data?.length">
                            <td colspan="10" class="px-4 py-10 text-center text-gray-400">
                                <template v-if="search">
                                    No products match "{{ search }}".
                                </template>
                                <template v-else>
                                    No shop products found. Add products in
                                    <a href="/station/settings" class="text-orange-500 underline hover:text-orange-600">Station Settings</a>.
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Bottom bar: Showing X to Y + Pagination -->
            <div class="flex flex-wrap justify-between items-center gap-3 px-4 py-3 border-t border-gray-200 bg-gray-50">
                <span class="text-gray-600">
                    <template v-if="products.total > 0">
                        Showing {{ products.from }} to {{ products.to }} of
                        {{ products.total }} entries
                        <span v-if="search" class="text-gray-400">(filtered)</span>
                    </template>
                    <template v-else>No entries found</template>
                </span>

                <div class="flex gap-0.5">
                    <!-- Previous -->
                    <span v-if="!products.prev_page_url"
                        class="px-3 py-1 border border-gray-300 rounded-l text-gray-400 bg-gray-100 cursor-default select-none">
                        Previous
                    </span>
                    <Link v-else :href="products.prev_page_url"
                        class="px-3 py-1 border border-gray-300 rounded-l text-gray-700 bg-white hover:bg-gray-100">
                        Previous
                    </Link>

                    <!-- Numbered pages -->
                    <template v-for="link in pageLinks" :key="link.label">
                        <span v-if="!link.url"
                            class="px-3 py-1 border-t border-b border-gray-300 text-gray-400 bg-white select-none">
                            …
                        </span>
                        <span v-else-if="link.active"
                            class="px-3 py-1 border-t border-b border-blue-500 bg-blue-500 text-white cursor-default select-none">
                            {{ link.label }}
                        </span>
                        <Link v-else :href="link.url"
                            class="px-3 py-1 border-t border-b border-gray-300 text-gray-700 bg-white hover:bg-gray-100">
                            {{ link.label }}
                        </Link>
                    </template>

                    <!-- Next -->
                    <span v-if="!products.next_page_url"
                        class="px-3 py-1 border border-gray-300 rounded-r text-gray-400 bg-gray-100 cursor-default select-none">
                        Next
                    </span>
                    <Link v-else :href="products.next_page_url"
                        class="px-3 py-1 border border-gray-300 rounded-r text-gray-700 bg-white hover:bg-gray-100">
                        Next
                    </Link>
                </div>
            </div>

        </div>

        <!-- ── GRN / Adj Modal ───────────────────────────────────────────── -->
        <div v-if="modalType"
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <div>
                        <h2 class="font-semibold text-gray-800">
                            {{ modalType === 'grn' ? 'Goods Received (GRN)' : 'Stock Adjustment' }}
                        </h2>
                        <p class="text-sm text-gray-500 mt-0.5">{{ modalProduct?.product_name }}</p>
                    </div>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitTx" class="px-6 py-5 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Date *</label>
                            <input type="date" v-model="txForm.trans_date" required
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                            <p v-if="txForm.errors.trans_date" class="text-xs text-red-500 mt-1">{{ txForm.errors.trans_date }}</p>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">
                                Quantity *
                                <span v-if="modalType === 'adj'" class="text-gray-400">(−&nbsp;to reduce)</span>
                            </label>
                            <input type="number" v-model="txForm.quantity" step="0.001"
                                :min="modalType === 'grn' ? 0.001 : undefined"
                                required
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                            <p v-if="txForm.errors.quantity" class="text-xs text-red-500 mt-1">{{ txForm.errors.quantity }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Stock Location</label>
                        <div class="flex gap-3">
                            <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                                <input type="radio" v-model="txForm.target" value="forecourt" class="accent-orange-500" />
                                Forecourt
                            </label>
                            <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                                <input type="radio" v-model="txForm.target" value="store" class="accent-orange-500" />
                                Store
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-600 mb-1">
                            {{ modalType === 'grn' ? 'Waybill / Invoice No' : 'Reference' }}
                        </label>
                        <input type="text" v-model="txForm.document_ref"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono"
                            placeholder="e.g. WB-0012345" />
                    </div>

                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Notes</label>
                        <input type="text" v-model="txForm.notes"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                            :placeholder="modalType === 'adj' ? 'e.g. Damaged, Expired…' : ''" />
                    </div>

                    <!-- Current stock display -->
                    <div class="bg-gray-50 rounded-lg px-4 py-3 text-sm flex justify-between">
                        <span class="text-gray-500">Current total stock:</span>
                        <span class="font-mono font-semibold">{{ fmt(modalProduct?.current_stock, 3) }} {{ modalProduct?.unit }}</span>
                    </div>

                    <div class="flex gap-3 pt-1">
                        <button type="submit" :disabled="txForm.processing"
                            class="flex-1 py-2 rounded-lg text-sm font-medium text-white transition-colors disabled:opacity-60"
                            :class="modalType === 'grn' ? 'bg-orange-500 hover:bg-orange-600' : 'bg-blue-600 hover:bg-blue-700'">
                            {{ txForm.processing ? 'Saving…' : (modalType === 'grn' ? 'Record GRN' : 'Save Adjustment') }}
                        </button>
                        <button type="button" @click="closeModal"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <ConfirmModal
            :show="confirmModal.show"
            :title="confirmModal.title"
            :message="confirmModal.message"
            variant="danger"
            @confirm="handleConfirm"
            @cancel="closeConfirm" />

    </AuthenticatedLayout>
</template>
