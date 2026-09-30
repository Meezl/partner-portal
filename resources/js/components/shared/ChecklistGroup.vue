<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Checklist } from '@/types/partner';

/**
 * A tick-box list with an "Other" free-text line, bound to a single object of
 * `{ [optionKey]: boolean, other: string | null }`. The option keys and labels
 * come from the server (App\Support\OnboardingChecklists) so the two stay in
 * step.
 */
const props = withDefaults(
    defineProps<{
        /** Option key → label, in the order they should appear. */
        options: Record<string, string>;
        modelValue: Checklist;
        /** Unique prefix for the input ids on the page. */
        idPrefix: string;
        /** Placeholder for the "Other" line. */
        otherPlaceholder?: string;
    }>(),
    { otherPlaceholder: 'Please specify' },
);

const emit = defineEmits<{ 'update:modelValue': [Checklist] }>();

const entries = computed(() => Object.entries(props.options));

/**
 * Held locally rather than derived from the prop on each change: two ticks in
 * the same tick would both build their new object from the prop the parent has
 * not re-rendered with yet, and the second would drop the first.
 */
const state = ref<Checklist>({ ...props.modelValue });

watch(
    () => props.modelValue,
    (value) => {
        state.value = { ...value };
    },
);

const otherChecked = computed(() => typeof state.value.other === 'string');

function commit(changes: Checklist) {
    state.value = { ...state.value, ...changes };
    emit('update:modelValue', { ...state.value });
}

function setOption(key: string, checked: boolean) {
    commit({ [key]: checked });
}

function setOtherChecked(checked: boolean) {
    commit({ other: checked ? (state.value.other ?? '') : null });
}

function setOtherText(value: string | number) {
    commit({ other: String(value) });
}
</script>

<template>
    <div class="space-y-3">
        <div v-for="[key, label] in entries" :key="key" class="flex items-start gap-3">
            <Checkbox
                :id="`${idPrefix}-${key}`"
                :model-value="state[key] === true"
                @update:model-value="setOption(key, $event === true)"
            />
            <Label :for="`${idPrefix}-${key}`" class="cursor-pointer text-sm font-normal leading-5">
                {{ label }}
            </Label>
        </div>

        <div class="flex items-start gap-3">
            <Checkbox
                :id="`${idPrefix}-other`"
                :model-value="otherChecked"
                @update:model-value="setOtherChecked($event === true)"
            />
            <div class="flex-1 space-y-2">
                <Label :for="`${idPrefix}-other`" class="cursor-pointer text-sm font-normal leading-5">
                    Other
                </Label>
                <Input
                    v-if="otherChecked"
                    :model-value="String(state.other ?? '')"
                    :placeholder="otherPlaceholder"
                    @update:model-value="setOtherText($event)"
                />
            </div>
        </div>
    </div>
</template>
