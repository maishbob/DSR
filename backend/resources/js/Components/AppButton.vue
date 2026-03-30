<script setup>
defineProps({
    variant: { type: String, default: 'primary' },   // primary | secondary | danger | ghost
    size: { type: String, default: 'md' },            // sm | md | lg
    processing: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    type: { type: String, default: 'button' },
});

const variantClasses = {
    primary:   'bg-orange-500 text-white hover:bg-orange-600 focus:ring-orange-400 shadow-sm',
    secondary: 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 focus:ring-gray-400',
    danger:    'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500 shadow-sm',
    ghost:     'text-gray-600 hover:text-gray-900 hover:bg-gray-100 focus:ring-gray-400',
};

const sizeClasses = {
    sm: 'px-3 py-1.5 text-xs rounded-lg',
    md: 'px-4 py-2 text-sm rounded-lg',
    lg: 'px-5 py-2.5 text-sm rounded-lg',
};
</script>

<template>
    <button
        :type="type"
        :disabled="processing || disabled"
        class="inline-flex items-center justify-center font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed"
        :class="[variantClasses[variant], sizeClasses[size]]">
        <span v-if="processing" class="flex items-center gap-2">
            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
            </svg>
            <slot name="processing">Processing...</slot>
        </span>
        <slot v-else />
    </button>
</template>
