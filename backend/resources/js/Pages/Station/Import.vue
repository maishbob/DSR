<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, reactive } from 'vue';

const props = defineProps({
    station: Object,
    counts: Object,
});

const page = usePage();
const flash = computed(() => page.props.flash);

// ── Import step definitions ──────────────────────────────────
const steps = [
    {
        key: 'products',
        title: 'Products',
        desc: 'products.csv — Product names, prices, and costs.',
        route: 'station.import.products',
        fields: [{ name: 'csv', label: 'products.csv', accept: '.csv,.txt' }],
        countKey: 'products',
    },
    {
        key: 'tanks',
        title: 'Tanks',
        desc: 'tanks.csv — Tank names, products, stock levels.',
        route: 'station.import.tanks',
        fields: [{ name: 'csv', label: 'tanks.csv', accept: '.csv,.txt' }],
        countKey: 'tanks',
        requires: 'products',
    },
    {
        key: 'pumps',
        title: 'Pumps / Nozzles',
        desc: 'pumps.csv — Nozzle names, tank assignments, last readings.',
        route: 'station.import.pumps',
        fields: [{ name: 'csv', label: 'pumps.csv', accept: '.csv,.txt' }],
        countKey: 'nozzles',
        requires: 'tanks',
    },
    {
        key: 'shifts',
        title: 'Daily Shifts',
        desc: 'dailyShifts.csv — Day/night shift records with fuel sales totals.',
        route: 'station.import.shifts',
        fields: [{ name: 'csv', label: 'dailyShifts.csv', accept: '.csv,.txt' }],
        countKey: 'shifts',
    },
    {
        key: 'readings',
        title: 'Pump Readings',
        desc: 'dailyPumpReadings.csv — Open/close meter readings per nozzle per shift.',
        route: 'station.import.readings',
        fields: [{ name: 'csv', label: 'dailyPumpReadings.csv', accept: '.csv,.txt' }],
        countKey: 'readings',
        requires: 'nozzles',
    },
    {
        key: 'credits',
        title: 'Credit Customers & Transactions',
        desc: 'clients.csv + clientTransactions.csv — Customer masterfile and credit sales/payments.',
        route: 'station.import.credits',
        fields: [
            { name: 'clients_csv', label: 'clients.csv', accept: '.csv,.txt' },
            { name: 'transactions_csv', label: 'clientTransactions.csv', accept: '.csv,.txt' },
        ],
        countKey: 'customers',
    },
    {
        key: 'oil-stock',
        title: 'Oil Stock (Shop Products)',
        desc: 'FctStock.csv — Oil/lubricant product names, prices, costs, and current stock.',
        route: 'station.import.oil-stock',
        fields: [{ name: 'csv', label: 'FctStock.csv', accept: '.csv,.txt' }],
        countKey: 'shop_products',
    },
    {
        key: 'oil-sales',
        title: 'Oil Daily Sales',
        desc: 'FctStockDailySales.csv — Daily oil/lubricant sales per shift.',
        route: 'station.import.oil-sales',
        fields: [{ name: 'csv', label: 'FctStockDailySales.csv', accept: '.csv,.txt' }],
        countKey: 'oil_sales',
        requires: 'shop_products',
    },
];

// ── Per-step form state ──────────────────────────────────────
const forms = reactive({});
const fileNames = reactive({});
const importing = reactive({});
const results = reactive({});

steps.forEach(step => {
    const data = {};
    step.fields.forEach(f => { data[f.name] = null; });
    forms[step.key] = useForm(data);
    fileNames[step.key] = {};
    step.fields.forEach(f => { fileNames[step.key][f.name] = ''; });
    importing[step.key] = false;
    results[step.key] = null;
});

function onFile(stepKey, fieldName, e) {
    const file = e.target.files[0];
    forms[stepKey][fieldName] = file;
    fileNames[stepKey][fieldName] = file?.name ?? '';
}

function allFilesSelected(step) {
    return step.fields.every(f => forms[step.key][f.name]);
}

function submit(step) {
    importing[step.key] = true;
    results[step.key] = null;
    forms[step.key].post(route(step.route), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: (p) => {
            results[step.key] = p.props.flash?.importStats ?? null;
        },
        onFinish: () => { importing[step.key] = false; },
    });
}

function depsMet(step) {
    if (!step.requires) return true;
    return (props.counts[step.requires] ?? 0) > 0;
}
</script>

<template>
    <Head title="Import Legacy Data" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Import Legacy Data</h1>
        </template>

        <div class="max-w-3xl mx-auto space-y-4">
            <!-- Station header -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-6 py-4 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-gray-800">{{ station.station_name }}</h2>
                    <p class="text-sm text-gray-500">Import data from the legacy Clarion system step by step.</p>
                </div>
                <div class="flex gap-3 text-xs text-gray-500">
                    <span class="bg-gray-100 rounded-full px-2.5 py-1">{{ counts.products }} products</span>
                    <span class="bg-gray-100 rounded-full px-2.5 py-1">{{ counts.tanks }} tanks</span>
                    <span class="bg-gray-100 rounded-full px-2.5 py-1">{{ counts.nozzles }} nozzles</span>
                    <span class="bg-gray-100 rounded-full px-2.5 py-1">{{ counts.shifts }} shifts</span>
                    <span class="bg-gray-100 rounded-full px-2.5 py-1">{{ counts.customers }} customers</span>
                    <span class="bg-gray-100 rounded-full px-2.5 py-1">{{ counts.shop_products }} oil products</span>
                    <span class="bg-gray-100 rounded-full px-2.5 py-1">{{ counts.oil_sales }} oil sales</span>
                </div>
            </div>

            <!-- Import steps -->
            <div v-for="(step, idx) in steps" :key="step.key"
                class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <span class="flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold"
                            :class="(counts[step.countKey] ?? 0) > 0
                                ? 'bg-green-100 text-green-700'
                                : 'bg-gray-100 text-gray-500'">
                            {{ (counts[step.countKey] ?? 0) > 0 ? '&#10003;' : idx + 1 }}
                        </span>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-800">{{ step.title }}</h3>
                            <p class="text-xs text-gray-500 mt-0.5">{{ step.desc }}</p>
                        </div>
                    </div>
                    <span v-if="(counts[step.countKey] ?? 0) > 0"
                        class="text-xs bg-green-50 text-green-700 rounded-full px-2.5 py-1 font-medium">
                        {{ counts[step.countKey] }} records
                    </span>
                </div>

                <!-- Dependency warning -->
                <div v-if="!depsMet(step)" class="px-6 py-3 bg-amber-50 border-b border-amber-100">
                    <p class="text-xs text-amber-700">
                        Import <strong>{{ step.requires }}</strong> first before importing {{ step.title.toLowerCase() }}.
                    </p>
                </div>

                <!-- File inputs + submit -->
                <form @submit.prevent="submit(step)" class="px-6 py-4">
                    <div class="space-y-3" :class="{ 'opacity-50 pointer-events-none': !depsMet(step) }">
                        <div v-for="field in step.fields" :key="field.name">
                            <label class="flex items-center gap-3 cursor-pointer border border-gray-200 rounded-lg px-4 py-2.5 hover:border-orange-300 transition-colors"
                                :class="{ 'border-orange-400 bg-orange-50': fileNames[step.key][field.name] }">
                                <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                                <span class="text-sm flex-1" :class="fileNames[step.key][field.name] ? 'text-gray-800 font-medium' : 'text-gray-500'">
                                    {{ fileNames[step.key][field.name] || `Choose ${field.label}...` }}
                                </span>
                                <input type="file" :accept="field.accept" class="hidden"
                                    @change="onFile(step.key, field.name, $event)" />
                            </label>
                            <p v-if="forms[step.key].errors[field.name]" class="text-xs text-red-500 mt-1">
                                {{ forms[step.key].errors[field.name] }}
                            </p>
                        </div>

                        <button type="submit"
                            :disabled="!allFilesSelected(step) || importing[step.key]"
                            class="inline-flex items-center gap-2 bg-orange-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-orange-600 transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg v-if="importing[step.key]" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            {{ importing[step.key] ? 'Importing...' : 'Import' }}
                        </button>
                    </div>
                </form>

                <!-- Results -->
                <div v-if="results[step.key]" class="px-6 py-3 border-t border-gray-100 bg-gray-50">
                    <div class="flex gap-4 text-sm">
                        <span v-for="(val, key) in results[step.key]" :key="key"
                            class="text-gray-700">
                            <strong>{{ val }}</strong>
                            <span class="text-gray-500 text-xs ml-1">{{ key.replace(/_/g, ' ') }}</span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Info box -->
            <div class="rounded-lg border border-blue-100 bg-blue-50 p-4">
                <h4 class="text-sm font-medium text-blue-800 mb-2">Import Order</h4>
                <p class="text-xs text-blue-700">
                    Follow the numbered steps in order: Products &rarr; Tanks &rarr; Pumps &rarr; Shifts &rarr; Readings &rarr; Credits &rarr; Oil Stock &rarr; Oil Sales.
                    Each step depends on data from the previous steps. You can re-run any step — duplicates are skipped automatically.
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
