<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CheckCircle, FileText, XCircle } from 'lucide-vue-next';
import { ref } from 'vue';
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import DataTable from '@/components/shared/DataTable.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
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
import type { AgreementStatus } from '@/types/partner';

defineOptions({ layout: AdminLayout });

interface ReviewAgreement {
    id: number;
    status: AgreementStatus;
    partner: { id: number; organization_name: string; contact_person: string; email: string } | null;
    package: string | null;
    signed_method: 'digital' | 'upload' | null;
    signed_by_name: string | null;
    signed_at: string | null;
    has_document: boolean;
    reviewer: string | null;
    reviewed_at: string | null;
    review_notes: string | null;
}

const props = defineProps<{
    agreements: ReviewAgreement[];
    status: AgreementStatus;
    counts: Record<AgreementStatus, number>;
}>();

const tabs: { status: AgreementStatus; label: string }[] = [
    { status: 'signed', label: 'Awaiting review' },
    { status: 'verified', label: 'Verified' },
    { status: 'rejected', label: 'Sent back' },
    { status: 'pending', label: 'Not yet signed' },
];

const verifying = ref<ReviewAgreement | null>(null);
const rejecting = ref<ReviewAgreement | null>(null);
const rejectForm = useForm({ reason: '' });

function verify() {
    if (!verifying.value) {
        return;
    }

    router.post(`/admin/agreements/${verifying.value.id}/verify`, {}, {
        preserveScroll: true,
        onFinish: () => {
            verifying.value = null;
        },
    });
}

function openReject(agreement: ReviewAgreement) {
    rejectForm.reset();
    rejectForm.clearErrors();
    rejecting.value = agreement;
}

function reject() {
    if (!rejecting.value) {
        return;
    }

    rejectForm.post(`/admin/agreements/${rejecting.value.id}/reject`, {
        preserveScroll: true,
        onSuccess: () => {
            rejecting.value = null;
            rejectForm.reset();
        },
    });
}

function formatDate(date: string | null): string {
    return date
        ? new Date(date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
        : '—';
}

const columns = [
    { key: 'partner', label: 'Partner' },
    { key: 'package', label: 'Package' },
    { key: 'signature', label: 'Signature' },
    { key: 'document', label: 'Document' },
    { key: 'review', label: props.status === 'signed' ? 'Signed' : 'Review' },
];
</script>

<template>
    <Head title="Agreement Review" />

    <div class="space-y-6">
        <div>
            <h1 class="font-heading text-2xl font-bold">Agreement Review</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Check each signed agreement before verifying it. Sending one back asks the
                partner to sign again and emails them your reason.
            </p>
        </div>

        <nav class="flex flex-wrap gap-2" aria-label="Filter by status">
            <Link
                v-for="tab in tabs"
                :key="tab.status"
                :href="`/admin/agreements?status=${tab.status}`"
                preserve-scroll
                class="inline-flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm transition-colors"
                :class="tab.status === status
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'bg-background hover:bg-muted'"
                :aria-current="tab.status === status ? 'page' : undefined"
            >
                {{ tab.label }}
                <span
                    class="rounded-full px-1.5 text-xs"
                    :class="tab.status === status ? 'bg-primary-foreground/20' : 'bg-muted'"
                >{{ counts[tab.status] ?? 0 }}</span>
            </Link>
        </nav>

        <DataTable
            :columns="columns"
            :data="agreements"
            :empty-message="status === 'signed' ? 'No signed agreements are waiting for review.' : 'No agreements here.'"
        >
            <template #partner="{ item }">
                <div class="font-medium">{{ item.partner?.organization_name ?? '—' }}</div>
                <div class="text-xs text-muted-foreground">{{ item.partner?.email }}</div>
            </template>
            <template #package="{ item }">
                {{ item.package ?? '—' }}
            </template>
            <template #signature="{ item }">
                <template v-if="item.signed_method">
                    <div>{{ item.signed_by_name ?? '—' }}</div>
                    <div class="text-xs text-muted-foreground">
                        {{ item.signed_method === 'digital' ? 'Signed digitally' : 'Uploaded signed copy' }}
                    </div>
                </template>
                <span v-else class="text-muted-foreground">—</span>
            </template>
            <template #document="{ item }">
                <Button v-if="item.has_document" as-child variant="ghost" size="sm">
                    <a :href="`/admin/agreements/${item.id}/document`" target="_blank" rel="noopener noreferrer">
                        <FileText class="mr-1 h-4 w-4" />
                        View
                    </a>
                </Button>
                <span v-else class="text-muted-foreground">—</span>
            </template>
            <template #review="{ item }">
                <template v-if="item.status === 'signed'">
                    <div>{{ formatDate(item.signed_at) }}</div>
                    <div v-if="item.review_notes" class="max-w-xs text-xs text-muted-foreground">
                        Previously sent back: {{ item.review_notes }}
                    </div>
                </template>
                <template v-else-if="item.reviewed_at">
                    <StatusBadge :status="item.status" />
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{ item.reviewer ?? 'Unknown' }} · {{ formatDate(item.reviewed_at) }}
                    </div>
                    <div v-if="item.status === 'rejected' && item.review_notes" class="mt-1 max-w-xs text-xs">
                        {{ item.review_notes }}
                    </div>
                </template>
                <span v-else class="text-muted-foreground">Awaiting partner signature</span>
            </template>
            <template #actions="{ item }">
                <div v-if="item.status === 'signed'" class="flex items-center justify-end gap-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        class="text-green-600 hover:text-green-700"
                        @click="verifying = item"
                    >
                        <CheckCircle class="mr-1 h-4 w-4" />
                        Verify
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="text-destructive hover:text-destructive"
                        @click="openReject(item)"
                    >
                        <XCircle class="mr-1 h-4 w-4" />
                        Reject
                    </Button>
                </div>
            </template>
        </DataTable>

        <ConfirmDialog
            :open="verifying !== null"
            title="Verify agreement"
            :description="`Confirm that ${verifying?.partner?.organization_name ?? 'this partner'}'s agreement is fully signed and complete. The partner will be notified.`"
            confirm-label="Verify agreement"
            @confirm="verify"
            @cancel="verifying = null"
        />

        <Dialog :open="rejecting !== null" @update:open="(v: boolean) => { if (!v) rejecting = null }">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Send agreement back</DialogTitle>
                    <DialogDescription>
                        {{ rejecting?.partner?.organization_name }} will be asked to sign again,
                        digitally or by uploading a new signed PDF. Their invoice is not affected.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-2 py-2">
                    <Label for="reject-reason">What needs fixing?</Label>
                    <textarea
                        id="reject-reason"
                        v-model="rejectForm.reason"
                        rows="4"
                        maxlength="1000"
                        class="border-input bg-background focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                        placeholder="This is emailed to the partner, so be specific — e.g. the witness section on page 3 is not signed."
                    />
                    <p v-if="rejectForm.errors.reason" class="text-destructive text-sm">
                        {{ rejectForm.errors.reason }}
                    </p>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="rejecting = null">Cancel</Button>
                    <Button
                        variant="destructive"
                        :disabled="!rejectForm.reason.trim() || rejectForm.processing"
                        @click="reject"
                    >
                        Send back
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
