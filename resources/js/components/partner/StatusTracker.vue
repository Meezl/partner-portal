<script setup lang="ts">
import { Check } from 'lucide-vue-next';
import { computed } from 'vue';
import type { PartnerStatus } from '@/types/partner';

const props = defineProps<{
    currentStatus: PartnerStatus;
}>();

interface Step {
    key: PartnerStatus;
    label: string;
}

const steps: Step[] = [
    { key: 'interest_submitted', label: 'Interest' },
    { key: 'pending_agreement', label: 'Commitment' },
    { key: 'pending_payment', label: 'Payment' },
    { key: 'onboarding', label: 'Onboarding' },
    { key: 'submitted', label: 'Submitted' },
    { key: 'scheduled', label: 'Scheduled' },
    { key: 'finalized', label: 'Finalized' },
];

const statusOrder: PartnerStatus[] = [
    'draft',
    'interest_submitted',
    'rejected',
    'pending_agreement',
    'pending_payment',
    'confirmed',
    'onboarding',
    'submitted',
    'scheduled',
    'finalized',
];

const currentIndex = computed(() => {
    return statusOrder.indexOf(props.currentStatus);
});

function stepState(step: Step): 'completed' | 'current' | 'upcoming' {
    const stepIdx = statusOrder.indexOf(step.key);

    if (stepIdx < currentIndex.value) {
        return 'completed';
    }

    if (stepIdx === currentIndex.value) {
        return 'current';
    }

    return 'upcoming';
}
</script>

<template>
    <div class="w-full overflow-x-auto">
        <div class="flex min-w-[500px] items-center">
            <template v-for="(step, index) in steps" :key="step.key">
                <!-- Connector line (before each step except the first) -->
                <div
                    v-if="index > 0"
                    class="h-0.5 flex-1"
                    :class="[
                        stepState(step) === 'completed' ||
                        stepState(step) === 'current'
                            ? 'bg-primary'
                            : 'bg-muted-foreground/20',
                    ]"
                />

                <!-- Step circle + label -->
                <div class="flex flex-col items-center gap-1.5">
                    <!-- Circle -->
                    <div
                        class="flex size-8 items-center justify-center rounded-full border-2 text-xs font-semibold transition-colors"
                        :class="{
                            'border-primary bg-primary text-primary-foreground':
                                stepState(step) === 'completed',
                            'border-primary bg-primary/10 text-primary ring-2 ring-primary/20':
                                stepState(step) === 'current',
                            'border-muted-foreground/30 bg-muted text-muted-foreground':
                                stepState(step) === 'upcoming',
                        }"
                    >
                        <Check
                            v-if="stepState(step) === 'completed'"
                            class="size-4"
                        />
                        <span v-else>{{ index + 1 }}</span>
                    </div>

                    <!-- Label -->
                    <span
                        class="text-xs font-medium whitespace-nowrap"
                        :class="{
                            'text-primary':
                                stepState(step) === 'completed' ||
                                stepState(step) === 'current',
                            'text-muted-foreground':
                                stepState(step) === 'upcoming',
                        }"
                    >
                        {{ step.label }}
                    </span>
                </div>
            </template>
        </div>
    </div>
</template>
