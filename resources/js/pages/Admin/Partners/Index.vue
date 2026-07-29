<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Search, Eye, Users } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import DataTable from '@/components/shared/DataTable.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectTrigger,
    SelectValue,
    SelectContent,
    SelectItem,
} from '@/components/ui/select';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type {
    Partner,
    SponsorshipPackage,
    PartnerStatus,
    PackageTier,
} from '@/types/partner';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    partners: (Partner & { packages?: SponsorshipPackage[] })[];
    partnersCount: number;
    filters: {
        status: PartnerStatus | null;
        search: string | null;
        package: PackageTier | null;
    };
}>();

const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || 'all');
const packageFilter = ref(props.filters.package || 'all');

let debounceTimer: ReturnType<typeof setTimeout>;

function applyFilters() {
    router.get(
        '/admin/partners',
        {
            search: search.value || undefined,
            status:
                statusFilter.value !== 'all' ? statusFilter.value : undefined,
            package:
                packageFilter.value !== 'all' ? packageFilter.value : undefined,
        },
        { preserveState: true, replace: true },
    );
}

watch(search, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 300);
});

watch([statusFilter, packageFilter], applyFilters);

const columns = [
    { key: 'organization_name', label: 'Organization' },
    { key: 'contact_person', label: 'Contact' },
    { key: 'email', label: 'Email' },
    { key: 'package', label: 'Package' },
    { key: 'status', label: 'Status' },
    { key: 'created_at', label: 'Submitted' },
];

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function getPackageName(
    partner: Partner & { packages?: SponsorshipPackage[] },
): string {
    return partner.packages?.[0]?.name ?? '---';
}
</script>

<template>
    <Head title="Partners" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="font-heading text-2xl font-bold">Partners</h1>
            <Badge variant="secondary">
                <Users class="mr-1 h-3 w-3" />
                {{ partnersCount }} total
            </Badge>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative max-w-sm min-w-[200px] flex-1">
                <Search
                    class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    placeholder="Search organizations..."
                    class="pl-9"
                />
            </div>

            <Select v-model="statusFilter">
                <SelectTrigger class="w-[180px]">
                    <SelectValue placeholder="Filter by status" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Statuses</SelectItem>
                    <SelectItem value="interest_submitted"
                        >Interest Submitted</SelectItem
                    >
                    <SelectItem value="rejected">Rejected</SelectItem>
                    <SelectItem value="pending_agreement"
                        >Pending Agreement</SelectItem
                    >
                    <SelectItem value="pending_payment"
                        >Pending Payment</SelectItem
                    >
                    <SelectItem value="confirmed">Confirmed</SelectItem>
                    <SelectItem value="onboarding">Onboarding</SelectItem>
                    <SelectItem value="submitted">Submitted</SelectItem>
                    <SelectItem value="scheduled">Scheduled</SelectItem>
                    <SelectItem value="finalized">Finalized</SelectItem>
                </SelectContent>
            </Select>

            <Select v-model="packageFilter">
                <SelectTrigger class="w-[180px]">
                    <SelectValue placeholder="Filter by package" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Packages</SelectItem>
                    <SelectItem value="gold">Gold</SelectItem>
                    <SelectItem value="silver">Silver</SelectItem>
                    <SelectItem value="cso">CSO</SelectItem>
                    <SelectItem value="exhibitor">Exhibitor</SelectItem>
                </SelectContent>
            </Select>
        </div>

        <!-- Table -->
        <DataTable
            :columns="columns"
            :data="partners"
            empty-message="No partners found matching your criteria."
        >
            <template #organization_name="{ item }">
                <div class="font-medium">{{ item.organization_name }}</div>
            </template>
            <template #package="{ item }">
                <span class="text-sm capitalize">{{
                    getPackageName(item)
                }}</span>
            </template>
            <template #status="{ item }">
                <StatusBadge :status="item.status" type="partner" />
            </template>
            <template #created_at="{ item }">
                <span class="text-sm text-muted-foreground">
                    {{
                        item.submitted_at
                            ? formatDate(item.submitted_at)
                            : formatDate(item.created_at)
                    }}
                </span>
            </template>
            <template #actions="{ item }">
                <Link :href="`/admin/partners/${item.id}`">
                    <Button variant="ghost" size="sm">
                        <Eye class="mr-1 h-4 w-4" />
                        View
                    </Button>
                </Link>
            </template>
        </DataTable>
    </div>
</template>
