<template>
    <div v-if="paginator && paginator.last_page > 1" class="mt-6 flex items-center justify-between">
        <div class="text-sm text-gray-600">
            Showing
            <span class="font-medium">{{ paginator.from }}</span>
            to
            <span class="font-medium">{{ paginator.to }}</span>
            of
            <span class="font-medium">{{ paginator.total }}</span>
        </div>
        <div class="flex gap-1">
            <Link
                v-for="link in paginator.links"
                :key="link.url"
                :href="link.url || '#'"
                :class="[
                    'px-3 py-1 text-xs rounded transition',
                    link.active
                        ? 'bg-orange-600 text-white'
                        : link.url
                          ? 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                          : 'bg-gray-100 text-gray-400 cursor-not-allowed',
                ]"
                v-html="link.label"
            />
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    paginator: {
        type: Object,
        required: true,
    },
});
</script>
