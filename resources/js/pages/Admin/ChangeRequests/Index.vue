<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CheckCircle, XCircle, ArrowLeftRight } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import DataTable from '@/components/shared/DataTable.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { ChangeRequest, ConferenceSession, Partner } from '@/types/partner';

defineOptions({ layout: AdminLayout });

type Paginated<T> = { data: T[]; total: number };

const props = defineProps<{
    changeRequests: Paginated<ChangeRequest & { session?: ConferenceSession; partner?: Partner }>;
    filters?: { status: string };
    pendingCount?: number;
}>();

const rows = computed(() => props.changeRequests.data ?? []);

/** Time requests carry a slot snapshot; everything else carries a free-text note. */
function valueLabel(value: Record<string, unknown> | null | undefined): string {
    if (!value) {
        return '---';
    }

    if (typeof value.label === 'string') {
        return value.slot_code ? `${value.slot_code} — ${value.label}` : value.label;
    }

    if (typeof value.note === 'string') {
        return value.note;
    }

    return '---';
}

const approvingId = ref<number | null>(null);
const rejectingId = ref<number | null>(null);
const resolutionNotes = ref('');

function approveRequest() {
    if (!approvingId.value) {
return;
}

    router.put(`/admin/change-requests/${approvingId.value}/approve`, {}, {
        onSuccess: () => {
 approvingId.value = null; 
},
    });
}

function openRejectDialog(id: number) {
    rejectingId.value = id;
    resolutionNotes.value = '';
}

function rejectRequest() {
    if (!rejectingId.value) {
return;
}

    router.put(`/admin/change-requests/${rejectingId.value}/reject`, {
        resolution_notes: resolutionNotes.value,
    }, {
        onSuccess: () => {
 rejectingId.value = null; resolutionNotes.value = ''; 
},
    });
}

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

const columns = [
    { key: 'partner', label: 'Partner' },
    { key: 'session', label: 'Session' },
    { key: 'type', label: 'Type' },
    { key: 'change', label: 'Requested change' },
    { key: 'reason', label: 'Reason' },
    { key: 'status', label: 'Status' },
    { key: 'created_at', label: 'Submitted' },
];
</script>

<template>
    <Head title="Change Requests" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="font-heading text-2xl font-bold">Change Requests</h1>
            <Badge variant="secondary">
                <ArrowLeftRight class="mr-1 h-3 w-3" />
                {{ pendingCount ?? rows.length }} pending
            </Badge>
        </div>

        <DataTable :columns="columns" :data="rows" empty-message="No change requests.">
            <template #partner="{ item }">
                <span class="font-medium">{{ item.partner?.organization_name ?? '---' }}</span>
            </template>
            <template #session="{ item }">
                {{ item.session?.title ?? '---' }}
            </template>
            <template #type="{ item }">
                <Badge variant="outline" class="capitalize">{{ String(item.type).replace(/_/g, ' ') }}</Badge>
            </template>
            <template #change="{ item }">
                <div class="space-y-0.5 text-sm">
                    <div class="font-medium">{{ valueLabel(item.requested_value) }}</div>
                    <div v-if="item.current_value" class="text-muted-foreground text-xs">
                        from {{ valueLabel(item.current_value) }}
                    </div>
                </div>
            </template>
            <template #reason="{ item }">
                <span class="max-w-[200px] truncate text-sm">{{ item.reason || '---' }}</span>
            </template>
            <template #status="{ item }">
                <StatusBadge :status="item.status" type="change_request" />
            </template>
            <template #created_at="{ item }">
                {{ formatDate(item.created_at) }}
            </template>
            <template #actions="{ item }">
                <div v-if="item.status === 'pending'" class="flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        class="text-green-600 hover:text-green-700"
                        @click="approvingId = item.id"
                    >
                        <CheckCircle class="mr-1 h-4 w-4" />
                        Approve
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="text-destructive hover:text-destructive"
                        @click="openRejectDialog(item.id)"
                    >
                        <XCircle class="mr-1 h-4 w-4" />
                        Reject
                    </Button>
                </div>
            </template>
        </DataTable>

        <!-- Approve Confirmation -->
        <ConfirmDialog
            :open="approvingId !== null"
            title="Approve Change Request"
            description="Approve this request? For a time change the requested slot is confirmed on the session and the partner's previous slot is released back to the pool."
            confirm-label="Approve"
            @confirm="approveRequest"
            @cancel="approvingId = null"
        />

        <!-- Reject Dialog with notes -->
        <Dialog :open="rejectingId !== null" @update:open="(val: boolean) => { if (!val) rejectingId = null }">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Reject Change Request</DialogTitle>
                    <DialogDescription>
                        Provide resolution notes to explain why this request is being rejected.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="resolution-notes">Resolution Notes</Label>
                        <textarea
                            id="resolution-notes"
                            v-model="resolutionNotes"
                            class="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            placeholder="Explain the reason for rejection..."
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="rejectingId = null">Cancel</Button>
                    <Button variant="destructive" @click="rejectRequest">
                        Reject Request
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
