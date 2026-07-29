import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import { toast } from 'vue-sonner';

export function useFlashMessages() {
    const page = usePage();

    watch(
        () => page.props.flash as { success?: string; error?: string },
        (flash) => {
            if (flash?.success) {
                toast.success(flash.success);
            }

            if (flash?.error) {
                toast.error(flash.error);
            }
        },
        { immediate: true },
    );
}
