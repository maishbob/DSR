<script setup>
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Confirm Action' },
    message: { type: String, default: 'Are you sure?' },
    confirmLabel: { type: String, default: 'Confirm' },
    cancelLabel: { type: String, default: 'Cancel' },
    variant: { type: String, default: 'danger' }, // 'danger' | 'warning' | 'info'
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'cancel']);

const variantClasses = {
    danger:  { icon: 'bg-red-100 text-red-600',   btn: 'bg-red-600 hover:bg-red-700 focus:ring-red-500' },
    warning: { icon: 'bg-orange-100 text-orange-600', btn: 'bg-orange-500 hover:bg-orange-600 focus:ring-orange-400' },
    info:    { icon: 'bg-blue-100 text-blue-600',  btn: 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500' },
};

const classes = variantClasses[props.variant] || variantClasses.danger;
</script>

<template>
    <Modal :show="show" max-width="md" @close="emit('cancel')">
        <div class="p-6">
            <div class="flex items-start gap-4">
                <!-- Icon -->
                <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center" :class="classes.icon">
                    <!-- Exclamation triangle -->
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </div>

                <!-- Content -->
                <div class="flex-1 min-w-0">
                    <h3 class="text-lg font-semibold text-gray-900">{{ title }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ message }}</p>
                </div>
            </div>

            <!-- Actions -->
            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    :disabled="processing"
                    @click="emit('cancel')"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400 transition-colors">
                    {{ cancelLabel }}
                </button>
                <button
                    type="button"
                    :disabled="processing"
                    @click="emit('confirm')"
                    class="px-4 py-2 text-sm font-medium text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 transition-colors disabled:opacity-60"
                    :class="classes.btn">
                    <span v-if="processing" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                        Processing...
                    </span>
                    <span v-else>{{ confirmLabel }}</span>
                </button>
            </div>
        </div>
    </Modal>
</template>
