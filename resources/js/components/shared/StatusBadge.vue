<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';

const props = defineProps<{
    status: string;
    type?: 'partner' | 'session' | 'payment' | 'invoice' | 'change_request';
}>();

type VariantStyle = {
    variant: 'default' | 'secondary' | 'destructive' | 'outline';
    class?: string;
};

const statusConfig = computed<VariantStyle>(() => {
    const s = props.status;

    // Destructive statuses
    if (['rejected', 'failed', 'cancelled', 'overdue'].includes(s)) {
        return { variant: 'destructive' };
    }

    // Warning statuses (amber)
    if (['pending_payment', 'pending_agreement', 'pending'].includes(s)) {
        return { variant: 'outline', class: 'border-warning bg-warning/10 text-warning' };
    }

    // Success / confirmed statuses
    if (['confirmed', 'approved', 'paid', 'signed', 'verified'].includes(s)) {
        return { variant: 'outline', class: 'border-success bg-success/10 text-success' };
    }

    // Info / scheduled statuses (blue/accent)
    if (['scheduled'].includes(s)) {
        return { variant: 'outline', class: 'border-accent bg-accent/10 text-accent' };
    }

    // Primary / finalized statuses (dark green)
    if (['finalized', 'completed'].includes(s)) {
        return { variant: 'default' };
    }

    // Submitted / interest statuses
    if (['interest_submitted', 'submitted', 'sent'].includes(s)) {
        return { variant: 'outline', class: 'border-primary bg-primary/10 text-primary' };
    }

    // Onboarding
    if (['onboarding'].includes(s)) {
        return { variant: 'secondary' };
    }

    // Draft / default
    if (['draft', 'auto_resolved'].includes(s)) {
        return { variant: 'outline' };
    }

    return { variant: 'outline' };
});

const label = computed(() => {
    return props.status
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
});
</script>

<template>
    <Badge
        :variant="statusConfig.variant"
        :class="statusConfig.class"
    >
        {{ label }}
    </Badge>
</template>
