<script setup>
import { ref, onBeforeUnmount } from 'vue';

const props = defineProps({
    url: { type: String, required: true },
    params: { type: Object, default: () => ({}) },
});

const open = ref(false);
const menuRef = ref(null);

function toggle() {
    open.value = !open.value;
}

function close() {
    open.value = false;
}

function buildUrl(format) {
    const u = new URL(props.url, window.location.origin);
    Object.entries(props.params).forEach(([k, v]) => {
        if (v !== null && v !== undefined && v !== '') u.searchParams.set(k, v);
    });
    u.searchParams.set('format', format);
    return u.toString();
}

function exportAs(format) {
    window.location.href = buildUrl(format);
    close();
}

function onDocClick(e) {
    if (menuRef.value && !menuRef.value.contains(e.target)) close();
}

document.addEventListener('click', onDocClick);
onBeforeUnmount(() => document.removeEventListener('click', onDocClick));
</script>

<template>
    <div class="relative inline-block" ref="menuRef">
        <button @click.stop="toggle" type="button"
            class="border border-gray-300 px-4 py-2 rounded-lg text-sm hover:bg-gray-50 inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" />
            </svg>
            Export
            <svg class="w-3 h-3 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div v-if="open"
            class="absolute right-0 mt-2 w-44 bg-white rounded-lg shadow-lg border border-gray-200 z-20 overflow-hidden">
            <button @click="exportAs('pdf')" type="button"
                class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-red-500"></span>
                PDF
            </button>
            <button @click="exportAs('xlsx')" type="button"
                class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50 flex items-center gap-2 border-t border-gray-100">
                <span class="w-2 h-2 rounded-full bg-green-500"></span>
                Excel (.xlsx)
            </button>
        </div>
    </div>
</template>
