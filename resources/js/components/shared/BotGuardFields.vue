<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

/**
 * The client half of BlockAutomatedSubmissions.
 *
 * Renders an off-screen decoy input and sends back the server's encrypted
 * stamp of when the page was rendered. Both are invisible and unreachable by
 * keyboard, so a person filling the form is never aware of them; something
 * filling every input it can find will complete the decoy, and something
 * submitting instantly will fail the timing check.
 *
 * The stamp is read once, when the form appears: a later partial reload must
 * not restart the clock on a form someone is already filling in.
 *
 * Positioned off-screen rather than `display:none` or `type=hidden`, because
 * simple scrapers skip inputs that are obviously not rendered.
 */
const renderedAt = usePage().props.botGuardToken ?? '';
</script>

<template>
    <div aria-hidden="true" class="pointer-events-none absolute -left-[9999px] h-0 w-0 overflow-hidden">
        <label for="website_url">Leave this field empty</label>
        <input
            id="website_url"
            name="website_url"
            type="text"
            tabindex="-1"
            autocomplete="off"
            :value="''"
            @input="(e: Event) => $emit('update:honeypot', (e.target as HTMLInputElement).value)"
        >
        <input name="form_loaded_at" type="text" tabindex="-1" autocomplete="off" :value="renderedAt" readonly>
    </div>
</template>
