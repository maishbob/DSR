<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    stations: Array,
    owners: Array,
});

// ── Modal state ────────────────────────────────────────────────
const showModal = ref(false);
const editing   = ref(null);

const form = ref({ owner_id: '', station_name: '', location: '', is_active: true });

function openCreate() {
    editing.value = null;
    form.value = { owner_id: props.owners[0]?.id || '', station_name: '', location: '', is_active: true };
    showModal.value = true;
}

function openEdit(station) {
    editing.value = station;
    form.value = {
        owner_id: station.owner_id,
        station_name: station.station_name,
        location: station.location || '',
        is_active: station.is_active,
    };
    showModal.value = true;
}

function save() {
    if (editing.value) {
        router.put(route('admin.stations.update', editing.value.id), form.value, {
            onSuccess: () => { showModal.value = false; },
        });
    } else {
        router.post(route('admin.stations.store'), form.value, {
            onSuccess: () => { showModal.value = false; },
        });
    }
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-lg font-semibold text-gray-800">Manage Stations</h1>
        </template>

        <div class="max-w-6xl mx-auto">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <p class="text-sm text-gray-500">{{ stations.length }} station(s)</p>
                <button @click="openCreate"
                    class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg shadow-sm transition-colors">
                    + New Station
                </button>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="text-left px-5 py-3 font-medium text-gray-600">Station</th>
                            <th class="text-left px-5 py-3 font-medium text-gray-600">Owner</th>
                            <th class="text-left px-5 py-3 font-medium text-gray-600">Location</th>
                            <th class="text-center px-5 py-3 font-medium text-gray-600">Products</th>
                            <th class="text-center px-5 py-3 font-medium text-gray-600">Tanks</th>
                            <th class="text-center px-5 py-3 font-medium text-gray-600">Nozzles</th>
                            <th class="text-center px-5 py-3 font-medium text-gray-600">Shifts</th>
                            <th class="text-center px-5 py-3 font-medium text-gray-600">Status</th>
                            <th class="text-right px-5 py-3 font-medium text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="station in stations" :key="station.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3.5 font-medium text-gray-900">{{ station.station_name }}</td>
                            <td class="px-5 py-3.5 text-gray-600">{{ station.owner?.name || '—' }}</td>
                            <td class="px-5 py-3.5 text-gray-600">{{ station.location || '—' }}</td>
                            <td class="px-5 py-3.5 text-center text-gray-600">{{ station.products_count }}</td>
                            <td class="px-5 py-3.5 text-center text-gray-600">{{ station.tanks_count }}</td>
                            <td class="px-5 py-3.5 text-center text-gray-600">{{ station.pump_nozzles_count }}</td>
                            <td class="px-5 py-3.5 text-center text-gray-600">{{ station.shifts_count }}</td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="station.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                                    {{ station.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <button @click="openEdit(station)"
                                    class="text-orange-600 hover:text-orange-700 text-xs font-medium hover:underline">
                                    Edit
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!stations.length">
                            <td colspan="9" class="px-5 py-8 text-center text-gray-400">No stations found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal -->
        <Teleport to="body">
            <Transition enter-from-class="opacity-0" leave-to-class="opacity-0"
                enter-active-class="transition duration-200" leave-active-class="transition duration-200">
                <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showModal = false">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6" @click.stop>
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">{{ editing ? 'Edit Station' : 'New Station' }}</h2>
                        <form @submit.prevent="save" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Owner</label>
                                <select v-model="form.owner_id" required
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-orange-500 focus:border-orange-500">
                                    <option v-for="owner in owners" :key="owner.id" :value="owner.id">{{ owner.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Station Name</label>
                                <input v-model="form.station_name" type="text" required
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-orange-500 focus:border-orange-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                                <input v-model="form.location" type="text"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-orange-500 focus:border-orange-500" />
                            </div>
                            <div v-if="editing" class="flex items-center gap-2">
                                <input v-model="form.is_active" type="checkbox" id="stationActive"
                                    class="rounded border-gray-300 text-orange-500 focus:ring-orange-500" />
                                <label for="stationActive" class="text-sm text-gray-700">Active</label>
                            </div>
                            <div class="flex justify-end gap-3 pt-2">
                                <button type="button" @click="showModal = false"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                                    Cancel
                                </button>
                                <button type="submit"
                                    class="px-4 py-2 text-sm font-medium text-white bg-orange-500 rounded-lg hover:bg-orange-600 transition-colors">
                                    {{ editing ? 'Save Changes' : 'Create Station' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>
