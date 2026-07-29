<script setup lang="ts">
import { computed  } from 'vue';
import type {Component} from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps<{
    title: string;
    value: string | number;
    description?: string;
    icon?: Component;
    trend?: 'up' | 'down' | 'neutral';
    trendValue?: string;
    variant?: 'default' | 'primary' | 'accent' | 'warning' | 'destructive';
}>();

const iconBgClass = computed(() => {
    switch (props.variant) {
        case 'primary':
            return 'bg-primary/10 text-primary';
        case 'accent':
            return 'bg-accent/10 text-accent';
        case 'warning':
            return 'bg-warning/10 text-warning';
        case 'destructive':
            return 'bg-destructive/10 text-destructive';
        default:
            return 'bg-muted text-muted-foreground';
    }
});
</script>

<template>
    <Card>
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium text-muted-foreground">
                {{ title }}
            </CardTitle>
            <div
                v-if="icon"
                class="flex h-8 w-8 items-center justify-center rounded-md"
                :class="iconBgClass"
            >
                <component :is="icon" class="h-4 w-4" />
            </div>
        </CardHeader>
        <CardContent>
            <div class="text-2xl font-bold">{{ value }}</div>
            <p v-if="description" class="mt-1 text-xs text-muted-foreground">
                {{ description }}
            </p>
            <p v-if="trendValue" class="mt-1 text-xs" :class="{
                'text-green-600': trend === 'up',
                'text-red-600': trend === 'down',
                'text-muted-foreground': trend === 'neutral',
            }">
                <span v-if="trend === 'up'">+</span>
                <span v-if="trend === 'down'">-</span>
                {{ trendValue }}
            </p>
        </CardContent>
    </Card>
</template>
