<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, ArrowRight } from 'lucide-vue-next';
import DataTable from '@/components/shared/DataTable.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { buildScheduleBoardPath } from '@/lib/scheduling-workflow.js';
import type { ConferenceSession, Partner } from '@/types/partner';

defineOptions({ layout: AdminLayout });

defineProps<{
    sessions: (ConferenceSession & { partner?: Partner })[];
}>();

const columns = [
    { key: 'title', label: 'Title' },
    { key: 'partner', label: 'Partner' },
    { key: 'format', label: 'Format' },
    { key: 'expected_participants', label: 'Expected Participants' },
    { key: 'status', label: 'Status' },
];
</script>

<template>
    <Head title="Sessions" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="font-heading text-2xl font-bold">Sessions</h1>
            <Badge variant="secondary">
                <CalendarDays class="mr-1 h-3 w-3" />
                {{ sessions.length }} total
            </Badge>
        </div>

        <DataTable :columns="columns" :data="sessions" empty-message="No sessions found.">
            <template #title="{ item }">
                <div class="max-w-[300px]">
                    <div class="font-medium">{{ item.title }}</div>
                    <div v-if="item.description" class="mt-0.5 truncate text-xs text-muted-foreground">
                        {{ item.description }}
                    </div>
                </div>
            </template>
            <template #partner="{ item }">
                <span class="text-sm">{{ item.partner?.organization_name ?? '---' }}</span>
            </template>
            <template #format="{ item }">
                <Badge variant="outline" class="capitalize">{{ item.format }}</Badge>
            </template>
            <template #expected_participants="{ item }">
                {{ item.expected_participants ?? '---' }}
            </template>
            <template #status="{ item }">
                <StatusBadge :status="item.status" type="session" />
            </template>
            <template #actions="{ item }">
                <Link :href="buildScheduleBoardPath(item.id)">
                    <Button variant="ghost" size="sm">
                        Assign
                        <ArrowRight class="ml-1 h-4 w-4" />
                    </Button>
                </Link>
            </template>
        </DataTable>
    </div>
</template>
