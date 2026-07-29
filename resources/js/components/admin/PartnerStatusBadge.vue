<script setup lang="ts">
import StatusBadge from '@/components/shared/StatusBadge.vue';

defineProps<{
    status: string;
}>();
</script>

<template>
    <StatusBadge :status="status" type="partner" />
</template>
