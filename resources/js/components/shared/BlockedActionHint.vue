<script setup lang="ts">
import { Info } from 'lucide-vue-next';

/**
 * Explains why an action is unavailable.
 *
 * A disabled button on its own tells someone that they cannot continue but not
 * what to do about it — the commonest report being "the submit button is greyed
 * out" with no idea that a required choice further up the page is missing.
 * Render this next to any button whose disabled state depends on a precondition
 * rather than on an in-flight request.
 */
defineProps<{
    /** Human-readable blockers. Empty means the action is available. */
    reasons: string[];
    /** Optional label for a button that takes the user to the blocker. */
    actionLabel?: string;
}>();

defineEmits<{ resolve: [] }>();
</script>

<template>
    <div
        v-if="reasons.length"
        class="flex items-start gap-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
        role="status"
    >
        <Info class="mt-0.5 h-3.5 w-3.5 shrink-0" />
        <div class="space-y-0.5">
            <p v-for="reason in reasons" :key="reason">{{ reason }}</p>
            <button
                v-if="actionLabel"
                type="button"
                class="font-medium underline underline-offset-2"
                @click="$emit('resolve')"
            >
                {{ actionLabel }}
            </button>
        </div>
    </div>
</template>
