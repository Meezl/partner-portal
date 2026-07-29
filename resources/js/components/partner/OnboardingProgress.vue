<script setup lang="ts">
import { computed } from 'vue';
import type { OnboardingProgress } from '@/types/partner';

const props = defineProps<{
    progress: OnboardingProgress;
}>();

interface Section {
    key: keyof OnboardingProgress;
    label: string;
}

const sections: Section[] = [
    { key: 'organization', label: 'Organization Details' },
    { key: 'sessions', label: 'Sessions' },
    { key: 'communications', label: 'Communications' },
    { key: 'contacts', label: 'Contacts' },
];

const overall = computed(() => {
    const values = sections.map((s) => props.progress[s.key]);
    const sum = values.reduce((a, b) => a + b, 0);

    return Math.round(sum / values.length);
});

function progressBarColor(value: number): string {
    if (value === 100) {
return 'bg-success';
}

    if (value >= 50) {
return 'bg-primary';
}

    if (value > 0) {
return 'bg-warning';
}

    return 'bg-muted-foreground/30';
}
</script>

<template>
    <div class="space-y-4">
        <!-- Overall progress -->
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium">Overall Completion</span>
            <span class="font-heading text-lg font-semibold" :class="overall === 100 ? 'text-success' : 'text-primary'">
                {{ overall }}%
            </span>
        </div>
        <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
            <div
                class="h-full rounded-full transition-all duration-500"
                :class="progressBarColor(overall)"
                :style="{ width: `${overall}%` }"
            />
        </div>

        <!-- Section progress bars -->
        <div class="mt-4 space-y-3">
            <div v-for="section in sections" :key="section.key" class="space-y-1">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-muted-foreground">{{ section.label }}</span>
                    <span class="text-sm font-medium">{{ progress[section.key] }}%</span>
                </div>
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                    <div
                        class="h-full rounded-full transition-all duration-500"
                        :class="progressBarColor(progress[section.key])"
                        :style="{ width: `${progress[section.key]}%` }"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
