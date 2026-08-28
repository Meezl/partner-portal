import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import { toast } from 'vue-sonner';

/**
 * Laravel's auth flows report through a top-level `status` page prop rather
 * than the session flash bag, so `useFlashMessages` never sees them. Some
 * statuses are human sentences ("We have emailed your password reset link.")
 * and some are machine keys that need translating.
 */
const STATUS_MESSAGES: Record<string, string> = {
    'verification-link-sent':
        'A new verification link has been sent to your email address.',
    'profile-updated': 'Your profile has been updated.',
    'password-updated': 'Your password has been updated.',
    'two-factor-authentication-enabled': 'Two-factor authentication is now enabled.',
    'two-factor-authentication-disabled': 'Two-factor authentication is now disabled.',
    'recovery-codes-generated': 'New recovery codes have been generated.',
};

export function useStatusMessages() {
    const page = usePage();

    watch(
        () => page.props.status as string | undefined,
        (status) => {
            if (!status) {
                return;
            }

            const mapped = STATUS_MESSAGES[status];

            if (mapped) {
                toast.success(mapped);

                return;
            }

            // Unmapped values are only shown when they read as a sentence.
            // This keeps enum-ish values ("confirmed", "scheduled") that other
            // pages may pass as `status` from being toasted as noise.
            if (status.includes(' ')) {
                toast.success(status);
            }
        },
        { immediate: true },
    );
}
