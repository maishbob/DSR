<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import { fmt, fmtDate } from '@/composables/useFormatters';
import { ref } from 'vue';

const props = defineProps({
    shifts: Object,
    date: String,
    station: Object,
});

const form = useForm({
    shift_date: new Date().toISOString().slice(0, 10),
    shift_type: 'day',
});

const showForm = ref(false);
const dateFilter = ref(props.date);

function submit() {
    form.post(route('shifts.store'), { onSuccess: () => { showForm.value = false; } });
}

function applyDateFilter() {
    router.get(route('shifts.index'), dateFilter.value ? { date: dateFilter.value } : {}, { preserveState: true });
}

function clearDateFilter() {
    dateFilter.value = '';
    router.get(route('shifts.index'), {}, { preserveState: true });
}

function statusClass(status) {
    return {
        open: 'bg-green-100 text-green-700',
        closed: 'bg-blue-100 text-blue-700',
        locked: 'bg-gray-200 text-gray-600',
    }[status] ?? 'bg-gray-100 text-gray-600';
}
</script>

<template>
    <Head title="Shifts" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Shifts</h1>
        </template>

        <!-- Filters + actions -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
            <div class="flex items-center gap-2">
                <label class="text-sm text-gray-600 font-medium">Filter by date:</label>
                <input type="date" v-model="dateFilter" @change="applyDateFilter"
                    class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:border-orange-400 focus:ring-2 focus:ring-orange-100 transition-colors" />
                <button v-if="dateFilter" @click="clearDateFilter"
                    class="text-xs text-gray-500 hover:text-gray-700 underline">Clear</button>
            </div>
            <button @click="showForm = !showForm"
                class="inline-flex items-center gap-1.5 bg-orange-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-orange-600 font-medium transition-colors shadow-sm shadow-orange-500/20">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Open New Shift
            </button>
        </div>

        <!-- Open shift form -->
        <Transition
            enter-active-class="transition-all duration-200"
            leave-active-class="transition-all duration-200"
            enter-from-class="opacity-0 -translate-y-2"
            leave-to-class="opacity-0 -translate-y-2">
            <div v-if="showForm" class="card p-6 mb-6">
                <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide mb-4">Open a Shift</h2>
                <form @submit.prevent="submit" class="flex flex-col sm:flex-row gap-4">
                    <div class="form-field">
                        <label>Date</label>
                        <input type="date" v-model="form.shift_date" />
                        <p v-if="form.errors.shift_date" class="text-red-500 text-xs mt-1">{{ form.errors.shift_date }}</p>
                    </div>
                    <div class="form-field">
                        <label>Shift Type</label>
                        <select v-model="form.shift_type">
                            <option value="day">Day Shift</option>
                            <option value="night">Night Shift</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" :disabled="form.processing"
                            class="bg-orange-500 text-white px-5 py-2 rounded-lg text-sm hover:bg-orange-600 disabled:opacity-60 font-medium transition-colors shadow-sm shadow-orange-500/20">
                            Open Shift
                        </button>
                        <button type="button" @click="showForm = false"
                            class="border border-gray-200 px-4 py-2 rounded-lg text-sm hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </Transition>

        <!-- Shifts table -->
        <div v-if="shifts.data?.length" class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left">
                            <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Shift</th>
                            <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">DSR #</th>
                            <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider text-right">Fuel Sales</th>
                            <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider text-right">Oil Sales</th>
                            <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">Expenses</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="shift in shifts.data" :key="shift.id" class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-4 py-3 font-medium text-gray-800">{{ fmtDate(shift.shift_date) }}</td>
                            <td class="px-4 py-3 capitalize text-gray-700">{{ shift.shift_type }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ shift.dsr_number ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="badge text-xs" :class="statusClass(shift.status)">{{ shift.status }}</span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-gray-700">
                                {{ shift.fuel_sales_total ? fmt(shift.fuel_sales_total) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-gray-700">
                                {{ shift.oil_sales_total ? fmt(shift.oil_sales_total) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-center text-gray-600">{{ shift.expenses_count }}</td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('shifts.show', shift.id)"
                                    class="text-xs bg-orange-500 text-white px-3 py-1 rounded-lg hover:bg-orange-600 font-medium transition-colors">
                                    Open
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="shifts.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                <p class="text-xs text-gray-500">
                    Showing {{ shifts.from }}–{{ shifts.to }} of {{ shifts.total }} shifts
                </p>
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
        <div v-else class="card">
            <div class="empty-state">
                <div class="empty-icon">
                    <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <p>{{ date ? `No shifts for ${date}.` : 'No shifts found.' }}</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
