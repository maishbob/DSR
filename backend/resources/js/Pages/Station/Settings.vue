<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { fmt, fmtDate } from '@/composables/useFormatters';

const props = defineProps({
    station: Object,
});

const tab = ref('pumps');

// ── Confirm modal ─────────────────────────────────────────────
const confirmModal = ref({ show: false, title: '', message: '', variant: 'danger', onConfirm: () => {} });
function openConfirm({ title, message, variant = 'danger', onConfirm }) {
    confirmModal.value = { show: true, title, message, variant, onConfirm };
}
function closeConfirm() { confirmModal.value.show = false; }
function handleConfirm() { confirmModal.value.onConfirm(); closeConfirm(); }

// ── Product form ──────────────────────────────────────────────────────────────
const productForm = useForm({ product_name: '', cost_per_litre: '' });
function addProduct() {
    productForm.post(route('station.products.store'), { onSuccess: () => productForm.reset() });
}

const editingProduct   = ref(null);
const showProductModal = ref(false);
const productEditForm  = useForm({ product_name: '', cost_per_litre: '', is_active: true });

const VAT_RATE = 16 / 116;   // Kenya VAT: 16% inclusive

function currentPriceForProduct(product) {
    return Number(product.price_histories?.[0]?.price_per_litre ?? 0);
}
function vatPerLitre(price) {
    return price * VAT_RATE;
}
function marginPerLitre(price, cost) {
    return price - cost;
}

function openEditProduct(product) {
    editingProduct.value  = product;
    productEditForm.product_name   = product.product_name;
    productEditForm.cost_per_litre = product.cost_per_litre ?? '';
    productEditForm.is_active      = product.is_active;
    showProductModal.value = true;
}

function saveProduct() {
    productEditForm.put(route('station.products.update', editingProduct.value.id), {
        onSuccess: () => { showProductModal.value = false; },
    });
}

// ── Tank form ─────────────────────────────────────────────────────────────────
const tankForm = useForm({
    product_id:          '',
    tank_name:           '',
    tank_capacity:       '',
    linked_tank_id:      '',
    is_active:           true,
    is_complex:          false,
    last_closing_stock:  '',
    last_dip_stock:      '',
    last_dip_2:          '',
});
const editingTank    = ref(null);
const showTankModal  = ref(false);

function openAddTank() {
    editingTank.value = null;
    tankForm.reset();
    showTankModal.value = true;
}

function openEditTank(tank) {
    editingTank.value = tank;
    tankForm.product_id         = String(tank.product_id);
    tankForm.tank_name          = tank.tank_name;
    tankForm.tank_capacity      = tank.tank_capacity ?? '';
    tankForm.linked_tank_id     = tank.linked_tank_id ?? '';
    tankForm.is_active          = tank.is_active ?? true;
    tankForm.is_complex         = tank.is_complex ?? false;
    tankForm.last_closing_stock = tank.last_closing_stock ?? '';
    tankForm.last_dip_stock     = tank.last_dip_stock ?? '';
    tankForm.last_dip_2         = tank.last_dip_2 ?? '';
    showTankModal.value = true;
}

function saveTank() {
    if (editingTank.value) {
        tankForm.put(route('station.tanks.update', editingTank.value.id), {
            onSuccess: () => { showTankModal.value = false; tankForm.reset(); },
        });
    } else {
        tankForm.post(route('station.tanks.store'), {
            onSuccess: () => { showTankModal.value = false; tankForm.reset(); },
        });
    }
}

// ── Price form ────────────────────────────────────────────────────────────────
const priceForm = useForm({ product_id: '', price_per_litre: '', effective_from: new Date().toISOString().slice(0, 10) });
function updatePrice() {
    priceForm.post(route('station.prices.store'), { onSuccess: () => priceForm.reset('price_per_litre') });
}

// ── Pumps (nozzle management) ─────────────────────────────────────────────────
const pumpsProductTab = ref(null);   // which product tab is active in the Pumps section

// Set default pumps product tab to first product
const pumpsProduct = computed(() => {
    if (pumpsProductTab.value) return pumpsProductTab.value;
    return props.station?.products?.[0]?.id ?? null;
});

const nozzlesForProduct = computed(() =>
    (props.station?.pump_nozzles ?? [])
        .filter(n => n.product_id == pumpsProduct.value)
        .sort((a, b) => a.sort_order - b.sort_order)
);

const tanksForProduct = computed(() =>
    (props.station?.tanks ?? []).filter(t => t.product_id == pumpsProduct.value)
);

// Nozzle form — for add and edit
const editingNozzle = ref(null);   // null = add mode, else the nozzle object being edited

const nozzleForm = useForm({
    product_id:  '',
    tank_id:     '',
    nozzle_ref:  '',
    nozzle_name: '',
    main_pump:   '',
    nozzle_no:   '',
    sort_order:  '',
    last_mech:   '',
    last_elec:   '',
    last_shs:    '',
});

function openAddNozzle() {
    editingNozzle.value = null;
    nozzleForm.reset();
    nozzleForm.product_id = String(pumpsProduct.value ?? '');
    showNozzleModal.value = true;
}

function openEditNozzle(nozzle) {
    editingNozzle.value = nozzle;
    nozzleForm.product_id  = String(nozzle.product_id);
    nozzleForm.tank_id     = String(nozzle.tank_id ?? '');
    nozzleForm.nozzle_ref  = nozzle.nozzle_ref ?? '';
    nozzleForm.nozzle_name = nozzle.nozzle_name;
    nozzleForm.last_mech   = nozzle.last_mech ?? '';
    nozzleForm.last_elec   = nozzle.last_elec ?? '';
    nozzleForm.last_shs    = nozzle.last_shs ?? '';
    nozzleForm.main_pump   = nozzle.main_pump ?? '';
    nozzleForm.nozzle_no   = nozzle.nozzle_no ?? '';
    nozzleForm.sort_order  = nozzle.sort_order ?? '';
    showNozzleModal.value = true;
}

function autoFillName() {
    if (nozzleForm.nozzle_ref) {
        const product = props.station?.products?.find(p => p.id == nozzleForm.product_id);
        if (product) {
            nozzleForm.nozzle_name = (nozzleForm.nozzle_ref + ' ' + product.product_name).toUpperCase();
        }
    }
}

const showNozzleModal = ref(false);

function saveNozzle() {
    if (editingNozzle.value) {
        nozzleForm.put(route('station.nozzles.update', editingNozzle.value.id), {
            onSuccess: () => { showNozzleModal.value = false; nozzleForm.reset(); },
        });
    } else {
        nozzleForm.post(route('station.nozzles.store'), {
            onSuccess: () => { showNozzleModal.value = false; nozzleForm.reset(); },
        });
    }
}

function deleteNozzle(nozzle) {
    openConfirm({
        title: 'Delete Nozzle',
        message: `Delete nozzle "${nozzle.nozzle_name}"? This cannot be undone.`,
        onConfirm: () => router.delete(route('station.nozzles.destroy', nozzle.id)),
    });
}

// ── Shop products ─────────────────────────────────────────────────────────────
const shopForm = useForm({
    product_name: '', unit: 'unit', current_price: '',
    cost: '', forecourt_stock: '0', store_stock: '0',
});
function addShopProduct() {
    shopForm.post(route('station.shop-products.store'), { onSuccess: () => shopForm.reset() });
}

const editingShopProduct  = ref(null);
const showShopModal       = ref(false);
const shopModalTab        = ref('stock');   // 'stock' | 'sales'

const shopEditForm = useForm({
    product_name: '', unit: 'unit', current_price: '',
    cost: '', forecourt_stock: '', store_stock: '', is_active: true,
});

const grnForm = useForm({
    type:         'grn',
    trans_date:   new Date().toISOString().slice(0, 10),
    quantity:     '',
    document_ref: '',
    notes:        '',
});

function openEditShop(sp) {
    editingShopProduct.value   = sp;
    shopEditForm.product_name  = sp.product_name;
    shopEditForm.unit          = sp.unit;
    shopEditForm.current_price = sp.current_price;
    shopEditForm.cost          = sp.cost ?? '';
    shopEditForm.forecourt_stock = sp.forecourt_stock;
    shopEditForm.store_stock     = sp.store_stock;
    shopEditForm.is_active       = sp.is_active;
    shopModalTab.value = 'stock';
    showShopModal.value = true;
}

function saveShopProduct() {
    shopEditForm.put(route('station.shop-products.update', editingShopProduct.value.id), {
        onSuccess: () => { showShopModal.value = false; },
    });
}

function submitGrn() {
    grnForm.post(route('stock-transactions.store', editingShopProduct.value.id), {
        onSuccess: () => grnForm.reset('quantity', 'document_ref', 'notes'),
    });
}

function deleteTransaction(id) {
    openConfirm({
        title: 'Delete Transaction',
        message: 'Delete this transaction?',
        onConfirm: () => router.delete(route('stock-transactions.destroy', id)),
    });
}

function totalByType(sp, type) {
    return (sp.stock_transactions ?? [])
        .filter(t => t.type === type)
        .reduce((s, t) => s + Number(t.quantity), 0);
}

function salesTotal(sp) {
    return (sp.oil_sales ?? []).reduce((s, t) => s + Number(t.quantity), 0);
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function fmtReading(n) {
    if (n == null || n === undefined) return '—';
    return Number(n).toFixed(3);
}
</script>

<template>
    <Head title="Station Settings" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Station Settings — {{ station.station_name }}</h1>
        </template>

        <!-- Main tabs -->
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <div class="flex border-b border-gray-200 mb-6">
                    <button v-for="t in ['pumps', 'tanks', 'products', 'prices', 'shop']" :key="t"
                        @click="tab = t"
                        class="px-5 py-2 text-sm font-medium border-b-2 -mb-px"
                        :class="tab === t ? 'border-orange-500 text-orange-500' : 'border-transparent text-gray-500 hover:text-gray-700'">
                        {{ { pumps: 'Pumps', tanks: 'Tanks', products: 'Products', prices: 'Fuel Prices', shop: 'Shop Products' }[t] }}
                    </button>
                </div>

                <!-- ── PUMPS ──────────────────────────────────────────────── -->
                <div v-if="tab === 'pumps'" class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <!-- Product sub-tabs -->
                    <div class="flex border-b border-gray-200 bg-gray-50">
                        <button v-for="p in (station.products ?? [])" :key="p.id"
                            @click="pumpsProductTab = p.id"
                            class="px-5 py-2 text-sm font-medium border-b-2 -mb-px"
                            :class="pumpsProduct == p.id
                                ? 'border-orange-500 text-orange-500 bg-white'
                                : 'border-transparent text-gray-500 hover:text-gray-700'">
                            {{ p.product_name }}
                        </button>
                    </div>

                    <div class="p-4">
                        <!-- Nozzle list -->
                        <table class="w-full text-sm mb-4">
                            <thead>
                                <tr class="bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                    <th class="px-3 py-2">Nozzle</th>
                                    <th class="px-3 py-2">Default Tank</th>
                                    <th class="px-3 py-2 text-center">Pump</th>
                                    <th class="px-3 py-2 text-center">Nozzle No</th>
                                    <th class="px-3 py-2 text-right">Last Mech</th>
                                    <th class="px-3 py-2 text-right">Last Elec</th>
                                    <th class="px-3 py-2 text-right">Last Shs</th>
                                    <th class="px-3 py-2 text-center">Status</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="nozzle in nozzlesForProduct" :key="nozzle.id"
                                    class="hover:bg-blue-50 cursor-pointer"
                                    @click="openEditNozzle(nozzle)">
                                    <td class="px-3 py-2 font-medium">{{ nozzle.nozzle_name }}</td>
                                    <td class="px-3 py-2 text-gray-600">{{ nozzle.tank?.tank_name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-center font-mono">{{ nozzle.main_pump ?? '—' }}</td>
                                    <td class="px-3 py-2 text-center font-mono">{{ nozzle.nozzle_no ?? '—' }}</td>
                                    <td class="px-3 py-2 text-right font-mono text-xs text-gray-500">
                                        {{ fmtReading(nozzle.last_mech) }}
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono text-xs text-gray-500">
                                        {{ fmtReading(nozzle.last_elec) }}
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono text-xs text-gray-500">
                                        {{ nozzle.last_shs != null ? Number(nozzle.last_shs).toFixed(2) : '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="px-1.5 py-0.5 rounded text-xs font-medium"
                                            :class="nozzle.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'">
                                            {{ nozzle.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2" @click.stop>
                                        <button @click="deleteNozzle(nozzle)"
                                            class="text-red-400 hover:text-red-600 text-xs">Delete</button>
                                    </td>
                                </tr>
                                <tr v-if="!nozzlesForProduct.length">
                                    <td colspan="9" class="px-3 py-6 text-center text-gray-400">
                                        No nozzles for this product. Click Add to create one.
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <button @click="openAddNozzle"
                            class="px-4 py-2 bg-blue-600 text-white rounded text-sm font-medium hover:bg-blue-700">
                            + Add Nozzle
                        </button>
                    </div>
                </div>

                <!-- ── TANKS ──────────────────────────────────────────────── -->
                <div v-if="tab === 'tanks'" class="space-y-4">
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 border-b"><tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Tank Name</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Product</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Capacity (L)</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Last Closing</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Last Dip</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Dip 2</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Linked</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Complex</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="t in station.tanks" :key="t.id"
                                    class="hover:bg-blue-50 cursor-pointer"
                                    @click="openEditTank(t)">
                                    <td class="px-4 py-3 font-medium text-gray-800">{{ t.tank_name }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ t.product?.product_name }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-xs">{{ Number(t.tank_capacity).toLocaleString() }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-xs text-gray-500">
                                        {{ t.last_closing_stock != null ? Number(t.last_closing_stock).toFixed(2) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-xs text-gray-500">
                                        {{ t.last_dip_stock != null ? Number(t.last_dip_stock).toFixed(2) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-xs text-gray-500">
                                        {{ t.last_dip_2 != null ? Number(t.last_dip_2).toFixed(2) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-500 text-xs">
                                        {{ station.tanks?.find(x => x.id === t.linked_tank_id)?.tank_name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span v-if="t.is_complex" class="px-1.5 py-0.5 text-xs rounded bg-purple-100 text-purple-700">Yes</span>
                                        <span v-else class="text-gray-400 text-xs">—</span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2 py-0.5 text-xs rounded-full"
                                            :class="t.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500'">
                                            {{ t.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="!station.tanks?.length">
                                    <td colspan="9" class="px-4 py-6 text-center text-gray-400">No tanks configured.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <button @click="openAddTank"
                        class="px-4 py-2 bg-blue-600 text-white rounded text-sm font-medium hover:bg-blue-700">
                        + Add Tank
                    </button>
                </div>

                <!-- Tank modal -->
                <div v-if="showTankModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
                    <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md mx-4">
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">
                            {{ editingTank ? 'Edit Tank' : 'Add Tank' }}
                        </h2>
                        <form @submit.prevent="saveTank" class="space-y-4">
                            <div v-if="!editingTank">
                                <label class="block text-xs text-gray-600 mb-1">Product *</label>
                                <select v-model="tankForm.product_id" required
                                    class="w-full border rounded px-3 py-2 text-sm">
                                    <option value="">— Select —</option>
                                    <option v-for="p in station.products" :key="p.id" :value="String(p.id)">{{ p.product_name }}</option>
                                </select>
                            </div>
                            <div v-else>
                                <label class="block text-xs text-gray-600 mb-1">Product</label>
                                <div class="border rounded px-3 py-2 text-sm bg-gray-50 text-gray-600">
                                    {{ station.products?.find(p => p.id == tankForm.product_id)?.product_name }}
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div class="col-span-2">
                                    <label class="block text-xs text-gray-600 mb-1">Tank Name *</label>
                                    <input v-model="tankForm.tank_name" type="text" required
                                        class="w-full border rounded px-3 py-2 text-sm uppercase"
                                        placeholder="DIESEL TANK 1" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Capacity (L) *</label>
                                    <input v-model="tankForm.tank_capacity" type="number" required min="1"
                                        class="w-full border rounded px-3 py-2 text-sm font-mono"
                                        placeholder="45000" />
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <input type="checkbox" v-model="tankForm.is_complex" id="is_complex"
                                    class="rounded border-gray-300 text-blue-600" />
                                <label for="is_complex" class="text-sm text-gray-700">Complex Tank</label>
                            </div>

                            <div class="border border-blue-200 bg-blue-50 rounded-lg p-3 space-y-3">
                                <div class="text-xs font-semibold text-blue-700">Opening Stock (used as opening for next shift)</div>
                                <div class="grid grid-cols-3 gap-2">
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">Last Closing</label>
                                        <input type="number" v-model="tankForm.last_closing_stock" step="0.01" min="0"
                                            class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm font-mono"
                                            placeholder="0.00" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">Last Dip</label>
                                        <input type="number" v-model="tankForm.last_dip_stock" step="0.01" min="0"
                                            class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm font-mono"
                                            placeholder="0.00" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">Dip 2</label>
                                        <input type="number" v-model="tankForm.last_dip_2" step="0.01" min="0"
                                            class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm font-mono"
                                            placeholder="0.00" />
                                    </div>
                                </div>
                            </div>

                            <div v-if="editingTank" class="flex items-center gap-2">
                                <input type="checkbox" v-model="tankForm.is_active" id="tank_is_active"
                                    class="rounded border-gray-300 text-blue-600" />
                                <label for="tank_is_active" class="text-sm text-gray-700">Active</label>
                            </div>

                            <div class="flex justify-end gap-3 pt-2">
                                <button type="button" @click="showTankModal = false"
                                    class="px-4 py-2 border rounded text-sm text-gray-600 hover:bg-gray-50">
                                    Cancel
                                </button>
                                <button type="submit" :disabled="tankForm.processing"
                                    class="px-6 py-2 bg-blue-600 text-white rounded text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                                    {{ editingTank ? 'OK' : 'Add' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ── PRODUCTS ───────────────────────────────────────────── -->
                <div v-if="tab === 'products'" class="space-y-6">
                    <!-- Add form -->
                    <div class="bg-white rounded-xl shadow-sm p-5">
                        <h2 class="font-semibold text-gray-700 mb-4">Add Product</h2>
                        <form @submit.prevent="addProduct" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Product Name *</label>
                                <input type="text" v-model="productForm.product_name" required
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                                    placeholder="e.g. DIESEL, UNLEADED" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Cost Per Litre (KES)</label>
                                <input type="number" v-model="productForm.cost_per_litre" step="0.0001" min="0"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                                    placeholder="0.0000" />
                            </div>
                            <div class="flex items-end">
                                <button type="submit" :disabled="productForm.processing"
                                    class="w-full bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60">
                                    Add
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Product list — click row to edit -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 border-b"><tr>
                                <th class="px-4 py-3 text-left text-gray-500">Product Name</th>
                                <th class="px-4 py-3 text-right text-gray-500">Price / L</th>
                                <th class="px-4 py-3 text-right text-gray-500">VAT / L</th>
                                <th class="px-4 py-3 text-right text-gray-500">Cost / L</th>
                                <th class="px-4 py-3 text-right text-gray-500">Margin</th>
                                <th class="px-4 py-3 text-left text-gray-500">Tanks</th>
                                <th class="px-4 py-3 text-left text-gray-500">Status</th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="p in station.products" :key="p.id"
                                    class="border-t border-gray-100 hover:bg-blue-50 cursor-pointer"
                                    @click="openEditProduct(p)">
                                    <td class="px-4 py-3 font-medium text-gray-800">{{ p.product_name }}</td>
                                    <td class="px-4 py-3 text-right font-mono">
                                        {{ currentPriceForProduct(p) > 0 ? fmt(currentPriceForProduct(p), 4) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-500">
                                        {{ currentPriceForProduct(p) > 0 ? fmt(vatPerLitre(currentPriceForProduct(p)), 4) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono">
                                        {{ p.cost_per_litre ? fmt(p.cost_per_litre, 4) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono"
                                        :class="marginPerLitre(currentPriceForProduct(p), Number(p.cost_per_litre ?? 0)) > 0
                                            ? 'text-green-700' : 'text-gray-400'">
                                        {{ (currentPriceForProduct(p) > 0 && p.cost_per_litre)
                                            ? fmt(marginPerLitre(currentPriceForProduct(p), Number(p.cost_per_litre)), 2)
                                            : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-500 text-xs">
                                        {{ p.tanks?.map(t => t.tank_name).join(', ') || '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 text-xs rounded-full"
                                            :class="p.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500'">
                                            {{ p.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="!station.products?.length">
                                    <td colspan="7" class="px-4 py-6 text-center text-gray-400">No products added yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ── PRICES ─────────────────────────────────────────────── -->
                <div v-if="tab === 'prices'" class="space-y-6">
                    <div class="bg-white rounded-xl shadow-sm p-5">
                        <h2 class="font-semibold text-gray-700 mb-4">Update Fuel Price</h2>
                        <p class="text-sm text-gray-500 mb-4">Setting a new price closes the previous price effective today.</p>
                        <form @submit.prevent="updatePrice" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Product *</label>
                                <select v-model="priceForm.product_id" required
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                    <option value="">Select product</option>
                                    <option v-for="p in station.products" :key="p.id" :value="p.id">{{ p.product_name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Price per Litre (KES) *</label>
                                <input type="number" v-model="priceForm.price_per_litre" required step="0.0001" min="0.001"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                                    placeholder="e.g. 198.5000" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Effective From *</label>
                                <div class="flex gap-2">
                                    <input type="date" v-model="priceForm.effective_from" required
                                        class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                                    <button type="submit" :disabled="priceForm.processing"
                                        class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60">
                                        Save
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="p-4 border-b border-gray-100"><h2 class="font-semibold text-gray-700">Price History</h2></div>
                        <template v-for="product in station.products" :key="product.id">
                            <div class="px-4 py-2 bg-gray-50 text-sm font-medium text-gray-600 border-t border-gray-100">
                                {{ product.product_name }}
                            </div>
                            <template v-for="ph in product.price_histories" :key="ph.id">
                                <div class="flex justify-between items-center px-4 py-2 border-t border-gray-50 text-sm">
                                    <span class="text-gray-600">From {{ fmtDate(ph.effective_from) }}</span>
                                    <span class="text-gray-400 text-xs">{{ ph.effective_to ? 'to ' + fmtDate(ph.effective_to) : 'current' }}</span>
                                    <span class="font-semibold text-gray-800">KES {{ fmt(ph.price_per_litre, 4) }}</span>
                                </div>
                            </template>
                            <div v-if="!product.price_histories?.length" class="px-4 py-3 text-sm text-gray-400">No prices set.</div>
                        </template>
                    </div>
                </div>

                <!-- ── SHOP PRODUCTS ──────────────────────────────────────── -->
                <div v-if="tab === 'shop'" class="space-y-6">
                    <!-- Add form -->
                    <div class="bg-white rounded-xl shadow-sm p-5">
                        <h2 class="font-semibold text-gray-700 mb-4">Add Shop Product</h2>
                        <form @submit.prevent="addShopProduct" class="grid grid-cols-2 sm:grid-cols-6 gap-3">
                            <div class="col-span-2">
                                <label class="block text-xs text-gray-600 mb-1">Item Name *</label>
                                <input type="text" v-model="shopForm.product_name" required
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm uppercase"
                                    placeholder="e.g. HX7 HELIX 10W-40 4L" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Unit *</label>
                                <input type="text" v-model="shopForm.unit" required
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Price *</label>
                                <input type="number" v-model="shopForm.current_price" required step="0.01" min="0"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono" placeholder="0.00" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Cost</label>
                                <input type="number" v-model="shopForm.cost" step="0.01" min="0"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono" placeholder="0.00" />
                            </div>
                            <div class="flex items-end">
                                <button type="submit" :disabled="shopForm.processing"
                                    class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60">
                                    Add
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Stock list — click to edit/manage -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 border-b"><tr>
                                <th class="px-4 py-3 text-left text-gray-500">Item Name</th>
                                <th class="px-4 py-3 text-right text-gray-500">Forecourt Stock</th>
                                <th class="px-4 py-3 text-right text-gray-500">Store Stock</th>
                                <th class="px-4 py-3 text-right text-gray-500">Current Stock</th>
                                <th class="px-4 py-3 text-right text-gray-500">Price</th>
                                <th class="px-4 py-3 text-right text-gray-500">Cost</th>
                                <th class="px-4 py-3 text-left text-gray-500">Status</th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="sp in station.shop_products" :key="sp.id"
                                    class="border-t border-gray-100 hover:bg-blue-50 cursor-pointer"
                                    @click="openEditShop(sp)">
                                    <td class="px-4 py-3 font-medium text-gray-800">{{ sp.product_name }}</td>
                                    <td class="px-4 py-3 text-right font-mono">{{ Number(sp.forecourt_stock).toLocaleString('en-KE', {minimumFractionDigits:0}) }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-500">{{ Number(sp.store_stock).toLocaleString('en-KE', {minimumFractionDigits:0}) }}</td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold">{{ Number(sp.current_stock).toLocaleString('en-KE', {minimumFractionDigits:0}) }}</td>
                                    <td class="px-4 py-3 text-right font-mono">{{ Number(sp.current_price).toLocaleString('en-KE', {minimumFractionDigits:2}) }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-500">{{ sp.cost ? Number(sp.cost).toLocaleString('en-KE', {minimumFractionDigits:2}) : '—' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 text-xs rounded-full"
                                            :class="sp.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500'">
                                            {{ sp.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="!station.shop_products?.length">
                                    <td colspan="7" class="px-4 py-6 text-center text-gray-400">No shop products configured.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- ── Shop Product Edit Modal ───────────────────────────────────── -->
        <div v-if="showShopModal"
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl flex flex-col max-h-[90vh]">
                <!-- Header -->
                <div class="px-6 pt-5 pb-3 border-b">
                    <h2 class="text-base font-semibold text-gray-800">{{ editingShopProduct?.product_name }}</h2>
                    <div class="flex gap-4 mt-2 text-sm">
                        <span>Forecourt Stock: <strong class="font-mono">{{ editingShopProduct?.forecourt_stock }}</strong></span>
                        <span>Store Stock: <strong class="font-mono">{{ editingShopProduct?.store_stock }}</strong></span>
                    </div>
                </div>

                <!-- Sub-tabs -->
                <div class="flex border-b border-gray-200 px-6 bg-gray-50">
                    <button @click="shopModalTab = 'stock'"
                        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px"
                        :class="shopModalTab === 'stock' ? 'border-orange-500 text-orange-500 bg-white' : 'border-transparent text-gray-500'">
                        Stock / GRN
                    </button>
                    <button @click="shopModalTab = 'sales'"
                        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px"
                        :class="shopModalTab === 'sales' ? 'border-orange-500 text-orange-500 bg-white' : 'border-transparent text-gray-500'">
                        Sales
                    </button>
                </div>

                <div class="overflow-y-auto flex-1 p-5">
                    <!-- Stock tab -->
                    <div v-if="shopModalTab === 'stock'" class="space-y-4">
                        <!-- Item details form -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="col-span-2">
                                <label class="block text-xs text-gray-600 mb-1">Item Name *</label>
                                <input v-model="shopEditForm.product_name" type="text" required
                                    class="w-full border rounded px-3 py-2 text-sm uppercase" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Price *</label>
                                <input v-model="shopEditForm.current_price" type="number" step="0.01" required
                                    class="w-full border rounded px-3 py-2 text-sm font-mono" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Cost</label>
                                <input v-model="shopEditForm.cost" type="number" step="0.01"
                                    class="w-full border rounded px-3 py-2 text-sm font-mono" placeholder="0.00" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Forecourt Stock</label>
                                <input v-model="shopEditForm.forecourt_stock" type="number" step="1" min="0"
                                    class="w-full border rounded px-3 py-2 text-sm font-mono" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Store Stock</label>
                                <input v-model="shopEditForm.store_stock" type="number" step="1" min="0"
                                    class="w-full border rounded px-3 py-2 text-sm font-mono" />
                            </div>
                        </div>

                        <!-- Transaction history -->
                        <div>
                            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Transaction History</div>
                            <div class="border rounded overflow-hidden">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase">
                                            <th class="px-3 py-2 text-left">Date</th>
                                            <th class="px-3 py-2 text-left">Doc Type</th>
                                            <th class="px-3 py-2 text-left">Ref</th>
                                            <th class="px-3 py-2 text-right">Quantity</th>
                                            <th class="px-3 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 max-h-48 overflow-y-auto">
                                        <tr v-for="tx in (editingShopProduct?.stock_transactions ?? [])" :key="tx.id"
                                            class="hover:bg-gray-50"
                                            :class="tx.type === 'grn' ? 'bg-green-50' : tx.type === 'adj' ? 'bg-yellow-50' : ''">
                                            <td class="px-3 py-1.5 text-xs">{{ tx.trans_date }}</td>
                                            <td class="px-3 py-1.5">
                                                <span class="px-1.5 py-0.5 rounded text-xs font-bold uppercase"
                                                    :class="tx.type === 'grn' ? 'bg-green-100 text-green-700'
                                                          : tx.type === 'iss' ? 'bg-red-100 text-red-700'
                                                          : 'bg-yellow-100 text-yellow-700'">
                                                    {{ tx.type }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-1.5 text-xs text-gray-500 font-mono">{{ tx.document_ref ?? '—' }}</td>
                                            <td class="px-3 py-1.5 text-right font-mono font-semibold"
                                                :class="tx.type === 'iss' ? 'text-red-600' : 'text-green-700'">
                                                {{ tx.type === 'iss' ? '-' : '+' }}{{ tx.quantity }}
                                            </td>
                                            <td class="px-3 py-1.5 text-right">
                                                <button v-if="tx.type !== 'iss'" @click="deleteTransaction(tx.id)"
                                                    aria-label="Delete transaction"
                                                    class="p-1 rounded text-red-400 hover:text-red-600 hover:bg-red-50 transition-colors">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr v-if="!(editingShopProduct?.stock_transactions ?? []).length">
                                            <td colspan="5" class="px-3 py-4 text-center text-gray-400 text-sm">No transactions yet</td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="bg-gray-50 border-t text-xs">
                                        <tr>
                                            <td class="px-3 py-1.5" colspan="3">Total GRN</td>
                                            <td class="px-3 py-1.5 text-right font-mono font-semibold text-green-700">
                                                {{ totalByType(editingShopProduct, 'grn') }}
                                            </td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td class="px-3 py-1.5" colspan="3">Total ISS</td>
                                            <td class="px-3 py-1.5 text-right font-mono font-semibold text-red-600">
                                                {{ totalByType(editingShopProduct, 'iss') }}
                                            </td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td class="px-3 py-1.5" colspan="3">Sales (DSR)</td>
                                            <td class="px-3 py-1.5 text-right font-mono">
                                                {{ salesTotal(editingShopProduct) }}
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- Insert GRN -->
                        <div class="border rounded p-3 bg-gray-50">
                            <div class="text-xs font-semibold text-gray-600 mb-2">Insert GRN / Adjustment</div>
                            <form @submit.prevent="submitGrn" class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Type</label>
                                    <select v-model="grnForm.type" class="w-full border rounded px-2 py-1.5 text-sm bg-white">
                                        <option value="grn">GRN</option>
                                        <option value="adj">ADJ</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Date</label>
                                    <input v-model="grnForm.trans_date" type="date"
                                        class="w-full border rounded px-2 py-1.5 text-sm bg-white" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Qty *</label>
                                    <input v-model="grnForm.quantity" type="number" step="1" min="1" required
                                        class="w-full border rounded px-2 py-1.5 text-sm font-mono bg-white" placeholder="0" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Ref / Doc No.</label>
                                    <input v-model="grnForm.document_ref"
                                        class="w-full border rounded px-2 py-1.5 text-sm font-mono bg-white" placeholder="GRN-001" />
                                </div>
                                <div class="flex items-end">
                                    <button type="submit" :disabled="grnForm.processing"
                                        class="w-full px-3 py-1.5 bg-green-600 text-white rounded text-sm hover:bg-green-700 disabled:opacity-50">
                                        Insert
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Sales tab -->
                    <div v-if="shopModalTab === 'sales'">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase">
                                    <th class="px-3 py-2 text-left">DSR No.</th>
                                    <th class="px-3 py-2 text-left">Date</th>
                                    <th class="px-3 py-2 text-right">Sales Qty</th>
                                    <th class="px-3 py-2 text-right">Price</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="sale in (editingShopProduct?.oil_sales ?? [])" :key="sale.id" class="hover:bg-gray-50">
                                    <td class="px-3 py-1.5 font-mono text-xs text-gray-500">
                                        {{ sale.shift?.daily_sales_record?.serial_number ?? '—' }}
                                    </td>
                                    <td class="px-3 py-1.5 text-xs">{{ sale.shift?.shift_date }}</td>
                                    <td class="px-3 py-1.5 text-right font-mono">{{ sale.quantity }}</td>
                                    <td class="px-3 py-1.5 text-right font-mono">{{ Number(sale.unit_price).toLocaleString('en-KE', {minimumFractionDigits:2}) }}</td>
                                </tr>
                                <tr v-if="!(editingShopProduct?.oil_sales ?? []).length">
                                    <td colspan="4" class="px-3 py-4 text-center text-gray-400">No sales recorded</td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-gray-50 border-t text-xs font-semibold">
                                <tr>
                                    <td class="px-3 py-2" colspan="2">Sales</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ salesTotal(editingShopProduct) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Footer actions -->
                <div class="px-5 py-3 border-t flex justify-between items-center bg-gray-50">
                    <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" v-model="shopEditForm.is_active" class="rounded" />
                        Active
                    </label>
                    <div class="flex gap-3">
                        <button @click="showShopModal = false"
                            class="px-4 py-2 border rounded text-sm text-gray-600 hover:bg-gray-100">
                            Cancel
                        </button>
                        <button @click="saveShopProduct" :disabled="shopEditForm.processing"
                            class="px-6 py-2 bg-blue-600 text-white rounded text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                            OK
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Product Edit Modal ────────────────────────────────────────── -->
        <div v-if="showProductModal"
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
                <h2 class="text-base font-semibold text-gray-800 mb-4">Change Product</h2>

                <form @submit.prevent="saveProduct" class="space-y-4">
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Product Name *</label>
                        <input v-model="productEditForm.product_name" type="text" required
                            class="w-full border rounded px-3 py-2 text-sm uppercase" />
                    </div>

                    <!-- Price / VAT / Cost / Margin display -->
                    <div class="border rounded overflow-hidden">
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-100">
                                <tr>
                                    <td class="px-3 py-2 text-gray-600 w-40">Price Per Litre</td>
                                    <td class="px-3 py-2 text-right font-mono font-semibold">
                                        {{ fmt(currentPriceForProduct(editingProduct), 4) }}
                                    </td>
                                    <td class="px-3 py-1 w-28">
                                        <span class="text-xs text-gray-400 italic">from price history</span>
                                    </td>
                                </tr>
                                <tr class="bg-gray-50">
                                    <td class="px-3 py-2 text-gray-600">VAT Per Litre</td>
                                    <td class="px-3 py-2 text-right font-mono text-gray-500">
                                        {{ fmt(vatPerLitre(currentPriceForProduct(editingProduct)), 4) }}
                                    </td>
                                    <td class="px-3 py-1">
                                        <span class="text-xs text-gray-400 italic">auto (16/116)</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2 text-gray-600">Cost Per Litre</td>
                                    <td class="px-3 py-2 text-right">
                                        <input v-model="productEditForm.cost_per_litre" type="number" step="0.0001" min="0"
                                            class="w-28 border rounded px-2 py-1 text-sm font-mono text-right" placeholder="0.0000" />
                                    </td>
                                    <td></td>
                                </tr>
                                <tr class="bg-gray-50">
                                    <td class="px-3 py-2 text-gray-600">Margin</td>
                                    <td class="px-3 py-2 text-right font-mono font-semibold"
                                        :class="marginPerLitre(currentPriceForProduct(editingProduct), Number(productEditForm.cost_per_litre || 0)) > 0
                                            ? 'text-green-700' : 'text-gray-400'">
                                        {{ productEditForm.cost_per_litre
                                            ? fmt(marginPerLitre(currentPriceForProduct(editingProduct), Number(productEditForm.cost_per_litre)), 2)
                                            : '—' }}
                                    </td>
                                    <td class="px-3 py-1">
                                        <span class="text-xs text-gray-400 italic">price − cost</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Tanks sub-list (read-only — managed in Tanks tab) -->
                    <div>
                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Tanks</div>
                        <div class="border rounded overflow-hidden">
                            <div v-for="tank in editingProduct?.tanks" :key="tank.id"
                                class="px-3 py-2 border-b last:border-b-0 text-sm text-gray-700">
                                {{ tank.tank_name }}
                            </div>
                            <div v-if="!editingProduct?.tanks?.length"
                                class="px-3 py-3 text-sm text-gray-400">
                                No tanks linked — add them in the Tanks tab.
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 pt-1">
                        <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                            <input type="checkbox" v-model="productEditForm.is_active" class="rounded" />
                            Active
                        </label>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="showProductModal = false"
                            class="px-4 py-2 border rounded text-sm text-gray-600 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" :disabled="productEditForm.processing"
                            class="px-6 py-2 bg-blue-600 text-white rounded text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                            OK
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── Nozzle Add/Edit Modal ──────────────────────────────────────── -->
        <div v-if="showNozzleModal"
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
                <h2 class="text-base font-semibold text-gray-800 mb-4">
                    {{ editingNozzle ? 'Change Nozzle' : 'Add Nozzle' }}
                </h2>

                <form @submit.prevent="saveNozzle" class="space-y-4">
                    <!-- Product (read-only when editing) -->
                    <div v-if="!editingNozzle">
                        <label class="block text-xs text-gray-600 mb-1">Product *</label>
                        <select v-model="nozzleForm.product_id" required
                            class="w-full border rounded px-3 py-2 text-sm">
                            <option value="">— Select —</option>
                            <option v-for="p in station.products" :key="p.id" :value="String(p.id)">{{ p.product_name }}</option>
                        </select>
                    </div>
                    <div v-else class="flex gap-3">
                        <div class="flex-1">
                            <label class="block text-xs text-gray-600 mb-1">Product</label>
                            <div class="border rounded px-3 py-2 text-sm bg-gray-50 text-gray-600">
                                {{ station.products?.find(p => p.id == nozzleForm.product_id)?.product_name }}
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Nozzle Ref</label>
                            <input v-model="nozzleForm.nozzle_ref" type="text"
                                @blur="autoFillName"
                                class="w-full border rounded px-3 py-2 text-sm font-mono uppercase"
                                placeholder="UX3" />
                            <p class="text-xs text-gray-400 mt-0.5">Short code e.g. UX3, DX1</p>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Full Name *</label>
                            <input v-model="nozzleForm.nozzle_name" type="text" required
                                class="w-full border rounded px-3 py-2 text-sm uppercase"
                                placeholder="UX3 UNLEADED" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Default Tank</label>
                        <select v-model="nozzleForm.tank_id"
                            class="w-full border rounded px-3 py-2 text-sm">
                            <option value="">— No tank assigned —</option>
                            <option v-for="t in station.tanks" :key="t.id" :value="String(t.id)">
                                {{ t.tank_name }} ({{ station.products?.find(p => p.id === t.product_id)?.product_name }})
                            </option>
                        </select>
                    </div>

                    <!-- Last / Opening readings — editable -->
                    <div v-if="editingNozzle" class="border border-blue-200 bg-blue-50 rounded-lg p-3 space-y-2">
                        <div class="text-xs font-semibold text-blue-700 mb-2">Opening Readings (used as opening for next shift)</div>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Mechanical</label>
                                <input type="number" v-model="nozzleForm.last_mech" step="0.1" min="0"
                                    class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm font-mono" placeholder="0.0" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Electrical</label>
                                <input type="number" v-model="nozzleForm.last_elec" step="0.001" min="0"
                                    class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm font-mono" placeholder="0.000" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Shs</label>
                                <input type="number" v-model="nozzleForm.last_shs" step="0.01" min="0"
                                    class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm font-mono" placeholder="0.00" />
                            </div>
                        </div>
                        <p class="text-xs text-blue-600">These become the opening readings when the next shift is opened.</p>
                    </div>

                    <!-- Legacy display (now hidden — kept as comment) -->
                    <div v-if="false && editingNozzle?.latest_reading" class="bg-gray-50 rounded p-3 text-xs text-gray-600 space-y-1">
                        <div class="font-semibold text-gray-700 mb-1">Last Recorded Readings</div>
                        <div class="flex justify-between">
                            <span>Mechanical:</span>
                            <span class="font-mono">{{ fmtReading(editingNozzle.latest_reading.closing_mechanical) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Electrical:</span>
                            <span class="font-mono">{{ fmtReading(editingNozzle.latest_reading.closing_electrical) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Shs:</span>
                            <span class="font-mono">
                                {{ editingNozzle.latest_reading.closing_shs != null
                                    ? Number(editingNozzle.latest_reading.closing_shs).toLocaleString('en-KE', {minimumFractionDigits:2})
                                    : '—' }}
                            </span>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="showNozzleModal = false"
                            class="px-4 py-2 border rounded text-sm text-gray-600 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" :disabled="nozzleForm.processing"
                            class="px-6 py-2 bg-blue-600 text-white rounded text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                            {{ editingNozzle ? 'OK' : 'Add' }}
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
