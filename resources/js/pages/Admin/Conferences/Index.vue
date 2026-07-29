<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus, Eye, CalendarDays } from 'lucide-vue-next';
import DataTable from '@/components/shared/DataTable.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { Conference } from '@/types/partner';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    conferences: (Conference & { partners_count?: number })[];
}>();

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

const columns = [
    { key: 'name', label: 'Name' },
    { key: 'year', label: 'Year' },
    { key: 'dates', label: 'Dates' },
    { key: 'venue', label: 'Venue' },
    { key: 'status', label: 'Status' },
    { key: 'partners_count', label: 'Partners' },
];
</script>

<template>
    <Head title="Conferences" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="font-heading text-2xl font-bold">Conferences</h1>
            <Link href="/admin/conferences/create">
                <Button>
                    <Plus class="mr-2 h-4 w-4" />
                    Create Conference
                </Button>
            </Link>
        </div>

        <DataTable :columns="columns" :data="conferences" empty-message="No conferences found.">
            <template #name="{ item }">
                <div class="flex items-center gap-2">
                    <CalendarDays class="h-4 w-4 text-muted-foreground" />
                    <span class="font-medium">{{ item.name }}</span>
                </div>
            </template>
            <template #dates="{ item }">
                <span class="text-sm">
                    {{ formatDate(item.start_date) }} - {{ formatDate(item.end_date) }}
                </span>
            </template>
            <template #status="{ item }">
                <StatusBadge :status="item.status" />
            </template>
            <template #partners_count="{ item }">
                <Badge variant="secondary">{{ item.partners_count ?? 0 }}</Badge>
            </template>
            <template #actions="{ item }">
                <Link :href="`/admin/conferences/${item.id}/edit`">
                    <Button variant="ghost" size="sm">
                        <Eye class="mr-1 h-4 w-4" />
                        Edit
                    </Button>
                </Link>
            </template>
        </DataTable>
    </div>
</template>
