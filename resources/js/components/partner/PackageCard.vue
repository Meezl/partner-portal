<script setup lang="ts">
import { Check } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter } from '@/components/ui/card';
import type { SponsorshipPackage, PackageTier } from '@/types/partner';

const props = withDefaults(
    defineProps<{
        package: SponsorshipPackage;
        selected?: boolean;
        selectable?: boolean;
    }>(),
    {
        selected: false,
        selectable: true,
    },
);

const emit = defineEmits<{
    select: [pkg: SponsorshipPackage];
}>();

const tierConfig = computed(() => {
    const configs: Record<PackageTier, { label: string; class: string; borderClass: string }> = {
        diamond: {
            label: 'Diamond',
            class: 'border-cyan-400 bg-cyan-50 text-cyan-700',
            borderClass: 'border-cyan-400',
        },
        platinum: {
            label: 'Platinum',
            class: 'border-slate-400 bg-slate-100 text-slate-700',
            borderClass: 'border-slate-400',
        },
        gold: {
            label: 'Gold',
            class: 'border-yellow-400 bg-yellow-50 text-yellow-700',
            borderClass: 'border-yellow-400',
        },
        silver: {
            label: 'Silver',
            class: 'border-gray-400 bg-gray-100 text-gray-600',
            borderClass: 'border-gray-400',
        },
        cso: {
            label: 'CSO',
            class: 'border-primary bg-primary/10 text-primary',
            borderClass: 'border-primary',
        },
        exhibitor: {
            label: 'Exhibitor',
            class: 'border-accent bg-accent/10 text-accent',
            borderClass: 'border-accent',
        },
    };

    return configs[props.package.tier] ?? configs.silver;
});

function formatPrice(amount: number, currency: string): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency,
        minimumFractionDigits: 0,
    }).format(amount);
}

function handleClick() {
    if (props.selectable) {
        emit('select', props.package);
    }
}
</script>

<template>
    <Card
        class="relative transition-all"
        :class="[
            selectable ? 'cursor-pointer hover:shadow-md' : '',
            selected ? `ring-2 ring-primary border-primary shadow-md` : 'border',
        ]"
        @click="handleClick"
    >
        <!-- Selected checkmark -->
        <div
            v-if="selected"
            class="absolute -right-2 -top-2 flex size-6 items-center justify-center rounded-full bg-primary text-primary-foreground"
        >
            <Check class="size-3.5" />
        </div>

        <CardHeader class="pb-3">
            <div class="flex items-center justify-between">
                <Badge variant="outline" :class="tierConfig.class">
                    {{ tierConfig.label }}
                </Badge>
                <span v-if="package.session_slots" class="text-xs text-muted-foreground">
                    {{ package.session_slots }} session{{ package.session_slots > 1 ? 's' : '' }}
                </span>
            </div>
            <CardTitle class="font-heading text-lg">{{ package.name }}</CardTitle>
            <CardDescription v-if="package.description">
                {{ package.description }}
            </CardDescription>
        </CardHeader>

        <CardContent class="pb-3">
            <div class="mb-3 font-heading text-2xl font-bold text-primary">
                {{ formatPrice(package.price, package.currency) }}
            </div>

            <ul v-if="package.benefits && package.benefits.length > 0" class="space-y-1.5">
                <li
                    v-for="(benefit, i) in package.benefits"
                    :key="i"
                    class="flex items-start gap-2 text-sm text-muted-foreground"
                >
                    <Check class="mt-0.5 size-3.5 shrink-0 text-primary" />
                    <span>{{ benefit }}</span>
                </li>
            </ul>
        </CardContent>

        <CardFooter v-if="package.exhibition_space" class="border-t pt-3">
            <p class="text-xs text-muted-foreground">
                Exhibition: {{ package.exhibition_space }}
            </p>
        </CardFooter>
    </Card>
</template>
