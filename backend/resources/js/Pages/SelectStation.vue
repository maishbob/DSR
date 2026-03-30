<script setup>
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    owner: Object,
    stations: Array,
});

function selectStation(stationId) {
    router.post(route('station.switch'), { station_id: stationId });
}
</script>

<template>
    <Head title="Select Station" />

    <div class="min-h-screen bg-gray-50 flex flex-col">
        <!-- Header -->
        <div class="bg-white border-b border-gray-200 px-6 py-4">
            <div class="max-w-5xl mx-auto flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center bg-orange-500">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v1.5M12 9v3.75m3-6.75v6.75" />
                        </svg>
                    </div>
                    <span class="font-bold text-gray-900 text-lg">DSR System</span>
                </div>
                <form @submit.prevent="router.post(route('logout'))">
                    <button type="submit" class="text-sm text-gray-500 hover:text-gray-700">Sign out</button>
                </form>
            </div>
        </div>

        <!-- Content -->
        <div class="flex-1 flex items-start justify-center px-6 py-12">
            <div class="w-full max-w-5xl">
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-gray-900">Welcome, {{ owner?.name }}</h1>
                    <p class="text-sm text-gray-500 mt-1">Select a station to manage</p>
                </div>

                <div v-if="stations?.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <button
                        v-for="station in stations"
                        :key="station.id"
                        @click="selectStation(station.id)"
                        class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 text-left hover:border-orange-400 hover:shadow-md transition-all group">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-orange-50 text-orange-500 group-hover:bg-orange-500 group-hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016A3.001 3.001 0 0021 9.349m-18 0V7.875C3 6.839 3.839 6 4.875 6h14.25C20.161 6 21 6.839 21 7.875v1.474" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="font-semibold text-gray-900 group-hover:text-orange-600 transition-colors">
                                    {{ station.station_name }}
                                </h3>
                                <p v-if="station.location" class="text-sm text-gray-500 mt-0.5 truncate">
                                    {{ station.location }}
                                </p>
                                <span class="inline-block mt-2 px-2 py-0.5 text-xs rounded-full"
                                    :class="station.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'">
                                    {{ station.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <svg class="w-5 h-5 text-gray-300 group-hover:text-orange-400 transition-colors mt-1" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                            </svg>
                        </div>
                    </button>
                </div>

                <div v-else class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                    <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016A3.001 3.001 0 0021 9.349m-18 0V7.875C3 6.839 3.839 6 4.875 6h14.25C20.161 6 21 6.839 21 7.875v1.474" />
                    </svg>
                    <p class="text-gray-500">No stations found. Please contact support.</p>
                </div>
            </div>
        </div>
    </div>
</template>
