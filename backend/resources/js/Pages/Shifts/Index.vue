<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    shifts: Array,
    date: String,
    station: Object,
});

const form = useForm({
    shift_date: props.date,
    shift_type: 'day',
});

const showForm = ref(false);

function submit() {
    form.post(route('shifts.store'), { onSuccess: () => { showForm.value = false; } });
}

function changeDate(d) {
    router.get(route('shifts.index'), { date: d });
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

        <!-- Date picker + actions -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
            <div class="flex items-center gap-2">
                <label class="text-sm text-gray-600">Date:</label>
                <input type="date" :value="date" @change="changeDate($event.target.value)"
                    class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm" />
            </div>
            <button @click="showForm = !showForm"
                class="bg-orange-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-orange-600 font-medium">
                + Open New Shift
            </button>
        </div>

        <!-- Open shift form -->
        <div v-if="showForm" class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h2 class="font-semibold text-gray-700 mb-4">Open a Shift</h2>
            <form @submit.prevent="submit" class="flex flex-col sm:flex-row gap-4">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Date</label>
                    <input type="date" v-model="form.shift_date"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-full" />
                    <p v-if="form.errors.shift_date" class="text-red-500 text-xs mt-1">{{ form.errors.shift_date }}</p>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Shift Type</label>
                    <select v-model="form.shift_type"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-full">
                        <option value="day">Day Shift</option>
                        <option value="night">Night Shift</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" :disabled="form.processing"
                        class="bg-orange-500 text-white px-5 py-2 rounded-lg text-sm hover:bg-orange-600 disabled:opacity-60">
                        Open Shift
                    </button>
                    <button type="button" @click="showForm = false"
                        class="border border-gray-300 px-4 py-2 rounded-lg text-sm hover:bg-gray-50">
                        Cancel
                    </button>
                </div>
            </form>
        </div>

        <!-- Shifts list -->
        <div v-if="shifts?.length" class="space-y-4">
            <div v-for="shift in shifts" :key="shift.id" class="bg-white rounded-xl shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <h3 class="font-semibold text-gray-800 capitalize">{{ shift.shift_type }} Shift</h3>
                        <span class="px-2 py-0.5 text-xs rounded-full font-medium" :class="statusClass(shift.status)">
                            {{ shift.status }}
                        </span>
                        <span v-if="shift.daily_sales_record?.locked"
                            class="px-2 py-0.5 text-xs bg-purple-100 text-purple-700 rounded-full">DSR Locked</span>
                    </div>
                    <Link :href="route('shifts.show', shift.id)"
                        class="text-sm bg-orange-500 text-white px-4 py-1.5 rounded-lg hover:bg-orange-600">
                        Open
                    </Link>
                </div>
                <div class="grid grid-cols-3 gap-4 text-sm text-gray-600">
                    <div>
                        <p class="text-gray-400 text-xs">Opened by</p>
                        <p>{{ shift.opened_by?.name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-xs">Meter readings</p>
                        <p>{{ shift.meter_readings?.length ?? 0 }} entered</p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-xs">Tank dips</p>
                        <p>{{ shift.tank_dips?.length ?? 0 }} entered</p>
                    </div>
                </div>
            </div>
        </div>
        <div v-else class="bg-white rounded-xl shadow-sm p-12 text-center">
            <p class="text-gray-400">No shifts for {{ date }}.</p>
        </div>
    </AuthenticatedLayout>
</template>
