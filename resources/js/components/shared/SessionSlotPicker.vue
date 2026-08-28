<script setup lang="ts">
import { CalendarClock, Clock, Lock, MapPin } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { parseCalendarDate } from '@/lib/utils';
import type { SessionSlot } from '@/types/partner';

const props = defineProps<{
    slots: SessionSlot[];
    modelValue: number | null;
    /** Slot already signed off by the partnerships team. */
    approvedSlotId?: number | null;
    /** Slot awaiting a decision. When set, the picker is read-only. */
    pendingSlotId?: number | null;
    /** Allow clearing the selection (creation only — see note in Edit). */
    allowClear?: boolean;
}>();

const emit = defineEmits<{ 'update:modelValue': [number | null] }>();

const locked = computed(() => props.pendingSlotId != null);

const slotsByDay = computed(() => {
    const groups = new Map<number, SessionSlot[]>();

    props.slots.forEach((slot) => {
        const bucket = groups.get(slot.day_index);

        if (bucket) {
            bucket.push(slot);
        } else {
            groups.set(slot.day_index, [slot]);
        }
    });

    return [...groups.entries()].sort(([a], [b]) => a - b);
});

function formatDate(date: string | null): string | null {
    if (!date) {
        return null;
    }

    return parseCalendarDate(date).toLocaleDateString(undefined, {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}

function dayHeading(index: number, slots: SessionSlot[]): string {
    const date = formatDate(slots[0]?.date ?? null);

    return date ? `Day ${index} — ${date}` : `Day ${index}`;
}

function select(slot: SessionSlot) {
    if (locked.value) {
        return;
    }

    emit('update:modelValue', props.allowClear && props.modelValue === slot.id ? null : slot.id);
}
</script>

<template>
    <div class="space-y-5">
        <div
            v-if="locked"
            class="flex items-start gap-3 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-800 dark:bg-amber-950/40"
        >
            <Lock class="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-500" />
            <p class="text-amber-900 dark:text-amber-200">
                Your requested date and time is awaiting approval from the partnerships team.
                You can keep editing the rest of the session, but the time is locked until they decide.
            </p>
        </div>

        <p v-if="!slots.length" class="text-muted-foreground text-sm">
            No bookable slots are currently available. The programme team will assign one manually.
        </p>

        <div v-for="[index, daySlots] in slotsByDay" :key="index" class="space-y-2">
            <h4 class="flex items-center gap-2 text-sm font-semibold">
                <CalendarClock class="text-muted-foreground h-4 w-4" />
                {{ dayHeading(index, daySlots) }}
            </h4>

            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                <label
                    v-for="slot in daySlots"
                    :key="slot.id"
                    class="flex flex-col gap-1.5 rounded-md border p-3 text-sm transition"
                    :class="[
                        modelValue === slot.id ? 'border-primary bg-primary/5' : 'border-input',
                        locked ? 'cursor-not-allowed opacity-60' : 'hover:border-primary cursor-pointer',
                    ]"
                >
                    <input
                        type="radio"
                        class="sr-only"
                        :value="slot.id"
                        :checked="modelValue === slot.id"
                        :disabled="locked"
                        @change="select(slot)"
                    />

                    <div class="flex items-start justify-between gap-2">
                        <span class="font-medium">{{ slot.slot_code }}</span>
                        <Badge v-if="slot.id === pendingSlotId" variant="outline" class="border-amber-400 text-amber-700 dark:text-amber-400">
                            Pending
                        </Badge>
                        <Badge v-else-if="slot.id === approvedSlotId" variant="outline" class="border-green-500 text-green-700 dark:text-green-400">
                            Approved
                        </Badge>
                    </div>

                    <span class="text-muted-foreground flex items-center gap-1.5 text-xs">
                        <Clock class="h-3 w-3" />
                        {{ slot.time_label }}
                        <template v-if="slot.track_label">· {{ slot.track_label }}</template>
                    </span>

                    <span v-if="slot.default_room" class="text-muted-foreground flex items-center gap-1.5 text-xs">
                        <MapPin class="h-3 w-3" />
                        {{ slot.default_room.name }}
                    </span>

                    <span v-if="slot.default_format" class="text-muted-foreground text-xs capitalize">
                        Setup: {{ slot.default_format }}
                    </span>
                </label>
            </div>
        </div>

        <button
            v-if="allowClear && modelValue && !locked"
            type="button"
            class="text-muted-foreground hover:text-foreground text-xs underline"
            @click="emit('update:modelValue', null)"
        >
            Clear selection
        </button>
    </div>
</template>
