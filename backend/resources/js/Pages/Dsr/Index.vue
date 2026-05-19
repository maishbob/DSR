<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { fmt, fmtDate } from '@/composables/useFormatters';

const props = defineProps({
    shifts: Object,
    station: Object,
    date:   String,
    search: String,
});

const page         = usePage();
const isManager    = computed(() => ['owner', 'manager'].includes(page.props.auth.user?.role));
const dateFilter   = ref(props.date);
const searchFilter = ref(props.search);
const showForm     = ref(false);

const confirmModal = ref({ show: false, title: '', message: '', onConfirm: () => {} });
function openConfirm(opts) { confirmModal.value = { show: true, ...opts }; }
function closeConfirm() { confirmModal.value.show = false; }
function handleConfirm() { confirmModal.value.onConfirm(); closeConfirm(); }

function isEmpty(shift) {
    return (shift.meter_readings_count + shift.oil_sales_count + shift.credit_sales_count +
            shift.expenses_count + shift.card_payments_count + shift.pos_transactions_count +
            shift.deliveries_count + shift.tank_dips_count) === 0 && !shift.daily_sales_record;
}

function deleteShift(shift) {
    openConfirm({
        title: 'Delete DSR',
        message: `Delete DSR #${shift.dsr_number ?? shift.id}? This cannot be undone.`,
        variant: 'danger',
        onConfirm: () => router.delete(route('shifts.destroy', shift.id), {
            onSuccess: () => {},
        }),
    });
}

const openForm = useForm({
    shift_date: new Date().toISOString().slice(0, 10),
    shift_type: 'day',
});

function submitOpen() {
    openForm.post(route('shifts.store'), {
        onSuccess: () => { showForm.value = false; },
    });
}

function applyFilter() {
    router.get(route('dsr.index'), {
        date:   dateFilter.value || undefined,
        search: searchFilter.value || undefined,
    }, { preserveState: true, replace: true });
}

function viewLink(shift) {
    return shift.dsr_number
        ? route('dsr.view-by-number', shift.dsr_number)
        : route('shifts.show', shift.id);
}

function statusClass(shift) {
    if (shift.daily_sales_record?.locked)  return 'bg-purple-100 text-purple-700';
    if (shift.daily_sales_record)          return 'bg-blue-100 text-blue-700';
    if (shift.status === 'locked')         return 'bg-red-100 text-red-700';
    if (shift.status === 'closed')         return 'bg-yellow-100 text-yellow-700';
    return 'bg-green-100 text-green-700';
}

function statusLabel(shift) {
    if (shift.daily_sales_record?.locked)  return 'Approved';
    if (shift.daily_sales_record)          return 'Draft';
    if (shift.status === 'locked')         return 'Locked';
    if (shift.status === 'closed')         return 'Closed';
    return 'Open';
}
</script>

<template>
    <Head title="Daily Sales Records" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-800">Daily Sales Records</h1>
                <button @click="showForm = !showForm"
                    class="inline-flex items-center gap-1.5 bg-orange-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-orange-600 font-medium shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    New DSR
                </button>
            </div>
        </template>

        <!-- Open shift form -->
        <Transition enter-active-class="transition-all duration-150" leave-active-class="transition-all duration-150"
            enter-from-class="opacity-0 -translate-y-2" leave-to-class="opacity-0 -translate-y-2">
            <div v-if="showForm" class="bg-white rounded-xl shadow-sm p-5 mb-4">
                <h2 class="text-sm font-semibold text-gray-700 mb-4">Open New DSR</h2>
                <form @submit.prevent="submitOpen" class="flex flex-wrap gap-4">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Date</label>
                        <input type="date" v-model="openForm.shift_date"
                            class="border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                        <p v-if="openForm.errors.shift_date" class="text-red-500 text-xs mt-1">{{ openForm.errors.shift_date }}</p>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Type</label>
                        <select v-model="openForm.shift_type"
                            class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="day">Day</option>
                            <option value="night">Night</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" :disabled="openForm.processing"
                            class="bg-orange-500 text-white px-5 py-2 rounded-lg text-sm hover:bg-orange-600 disabled:opacity-60 font-medium">
                            Open
                        </button>
                        <button type="button" @click="showForm = false"
                            class="border border-gray-200 px-4 py-2 rounded-lg text-sm hover:bg-gray-50">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </Transition>

        <!-- Filters -->
        <div class="mb-4 flex flex-wrap gap-3">
            <input v-model="dateFilter" type="date"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm"
                @change="applyFilter" />
            <input v-model="searchFilter" type="text" placeholder="Search DSR #…"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-36"
                @keyup.enter="applyFilter" />
            <button @click="applyFilter"
                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200">
                Search
            </button>
            <Link v-if="date || search" :href="route('dsr.index')"
                class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50 text-gray-600">
                Clear
            </Link>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">DSR #</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Shift</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Fuel Sales</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Oil Sales</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="shift in shifts.data" :key="shift.id"
                        class="border-t border-gray-100 hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3 font-mono font-semibold text-gray-800">
                            {{ shift.dsr_number ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-gray-700">{{ fmtDate(shift.shift_date) }}</td>
                        <td class="px-4 py-3 capitalize text-gray-600">{{ shift.shift_type }}</td>
                        <td class="px-4 py-3 text-right font-mono text-gray-700">
                            {{ shift.fuel_sales_total != null ? fmt(shift.fuel_sales_total) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-gray-700">
                            {{ shift.oil_sales_total > 0 ? fmt(shift.oil_sales_total) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 text-xs rounded-full font-medium" :class="statusClass(shift)">
                                {{ statusLabel(shift) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button v-if="isManager && isEmpty(shift)" @click="deleteShift(shift)"
                                    class="text-xs text-red-500 hover:text-red-700 px-2 py-1 rounded hover:bg-red-50"
                                    title="Delete empty DSR">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                                <Link :href="viewLink(shift)"
                                    class="text-xs bg-orange-500 text-white px-3 py-1 rounded-lg hover:bg-orange-600 font-medium">
                                    Open
                                </Link>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!shifts.data?.length">
                        <td colspan="7" class="px-4 py-10 text-center text-gray-400">No records found.</td>
                    </tr>
                </tbody>
            </table>

            <div v-if="shifts.last_page > 1" class="px-4 py-3 border-t flex justify-between items-center text-sm">
                <span class="text-gray-500">
                    Showing {{ shifts.from }}–{{ shifts.to }} of {{ shifts.total }}
                </span>
                <div class="flex gap-1">
                    <template v-for="link in shifts.links" :key="link.label">
                        <Link v-if="link.url" :href="link.url"
                            class="px-3 py-1 text-xs rounded-lg transition-colors"
                            :class="link.active ? 'bg-orange-500 text-white' : 'text-gray-600 hover:bg-gray-100'"
                            v-html="link.label" preserve-state />
                        <span v-else class="px-3 py-1 text-xs text-gray-300" v-html="link.label" />
                    </template>
                </div>
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
