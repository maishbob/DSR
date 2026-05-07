import { ref } from 'vue';

export function useConfirm() {
    const confirmModal = ref({
        show: false,
        title: '',
        message: '',
        variant: 'danger',
        onConfirm: () => {},
    });

    function openConfirm(title, message, onConfirm, variant = 'danger') {
        confirmModal.value = {
            show: true,
            title,
            message,
            variant,
            onConfirm,
        };
    }

    function closeConfirm() {
        confirmModal.value.show = false;
    }

    function handleConfirm() {
        confirmModal.value.onConfirm();
        closeConfirm();
    }

    return {
        confirmModal,
        openConfirm,
        closeConfirm,
        handleConfirm,
    };
}
