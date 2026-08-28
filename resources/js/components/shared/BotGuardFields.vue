<script setup lang="ts">
import { onMounted, ref } from 'vue';

/**
 * The client half of BlockAutomatedSubmissions.
 *
 * Renders an off-screen decoy input and stamps when the form appeared. Both are
 * invisible and unreachable by keyboard, so a person filling the form is never
 * aware of them; something filling every input it can find will complete the
 * decoy, and something submitting instantly will fail the timing check.
 *
 * Positioned off-screen rather than `display:none` or `type=hidden`, because
 * simple scrapers skip inputs that are obviously not rendered.
 */
const loadedAt = ref('');

onMounted(() => {
    loadedAt.value = String(Date.now());
});
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
        <input name="form_loaded_at" type="text" tabindex="-1" autocomplete="off" :value="loadedAt" readonly>
    </div>
</template>
