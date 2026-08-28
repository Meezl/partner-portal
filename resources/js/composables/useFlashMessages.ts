import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import { toast } from 'vue-sonner';

type Flash = {
    success?: string | null;
    error?: string | null;
    info?: string | null;
};

/**
 * Surfaces controller feedback as toasts.
 *
 * Every controller action that redirects with `->with('success'|'error'|'info')`
 * is reported here, so no create/update/delete can succeed or fail silently.
 * Call once per layout; the Toaster component must also be mounted.
 */
export function useFlashMessages() {
    const page = usePage();

    watch(
        () => page.props.flash as Flash | undefined,
        (flash) => {
            if (flash?.success) {
                toast.success(flash.success);
            }

            if (flash?.error) {
                toast.error(flash.error, { duration: 8000 });
            }

            if (flash?.info) {
                toast.info(flash.info);
            }
        },
        { immediate: true },
    );

    // Validation failures never reach `flash`. Forms with inline <InputError>
    // show the detail; this makes sure the ones without it (dialogs, selects)
    // still tell the user why nothing happened.
    watch(
        () => page.props.errors as Record<string, string> | undefined,
        (errors) => {
            const messages = Object.values(errors ?? {}).filter(Boolean);

            if (messages.length === 0) {
                return;
            }

            toast.error(messages[0], {
                description:
                    messages.length > 1
                        ? `and ${messages.length - 1} other problem${messages.length > 2 ? 's' : ''}`
                        : undefined,
                duration: 8000,
            });
        },
        // immediate: errors are already present when the page mounts after a
        // failed submit (and after any full page load), so without this the
        // very case we want to report would be the one that stays silent.
        { immediate: true },
    );
}
