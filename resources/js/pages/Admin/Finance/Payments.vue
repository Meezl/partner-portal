<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CreditCard, CheckCircle, XCircle, Clock, FileText } from 'lucide-vue-next';
import { ref } from 'vue';
import StatsCard from '@/components/admin/StatsCard.vue';
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import DataTable from '@/components/shared/DataTable.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { Payment, Invoice, Partner } from '@/types/partner';

defineOptions({ layout: AdminLayout });

defineProps<{
    payments: (Payment & {
        invoice?: Invoice;
        partner?: Partner;
    })[];
    stats: {
        pendingCount: number;
        confirmedTotal: number;
        pendingTotal: number;
    };
}>();

const confirmingPaymentId = ref<number | null>(null);
const rejectingPaymentId = ref<number | null>(null);

function confirmPayment() {
    if (!confirmingPaymentId.value) {
        return;
    }

    router.put(`/admin/finance/payments/${confirmingPaymentId.value}/confirm`, {}, {
        onSuccess: () => {
            confirmingPaymentId.value = null;
        },
    });
}

function rejectPayment() {
    if (!rejectingPaymentId.value) {
        return;
    }

    router.put(`/admin/finance/payments/${rejectingPaymentId.value}/reject`, {}, {
        onSuccess: () => {
            rejectingPaymentId.value = null;
        },
    });
}

function formatCurrency(amount: number, currency?: string): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency || 'USD',
        minimumFractionDigits: 0,
    }).format(amount);
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
    { key: 'invoice_number', label: 'Invoice #' },
    { key: 'amount', label: 'Amount' },
    { key: 'payment_method', label: 'Method' },
    { key: 'transaction_reference', label: 'Reference' },
    { key: 'supporting_document', label: 'Proof' },
    { key: 'status', label: 'Status' },
    { key: 'confirmed_at', label: 'Date' },
];
</script>

<template>
    <Head title="Payments" />

    <div class="space-y-6">
        <h1 class="font-heading text-2xl font-bold">Payments</h1>

        <!-- Stats -->
        <div class="grid gap-4 sm:grid-cols-3">
            <StatsCard
                title="Pending Payments"
                :value="stats.pendingCount"
                :icon="Clock"
                variant="warning"
            />
            <StatsCard
                title="Confirmed Total"
                :value="formatCurrency(stats.confirmedTotal)"
                :icon="CheckCircle"
                variant="primary"
            />
            <StatsCard
                title="Pending Total"
                :value="formatCurrency(stats.pendingTotal)"
                :icon="CreditCard"
                variant="accent"
            />
        </div>

        <!-- Table -->
        <DataTable :columns="columns" :data="payments" empty-message="No payments found.">
            <template #partner="{ item }">
                <span class="font-medium">{{ item.partner?.organization_name ?? '---' }}</span>
            </template>
            <template #invoice_number="{ item }">
                {{ item.invoice?.invoice_number ?? '---' }}
            </template>
            <template #amount="{ item }">
                {{ formatCurrency(item.amount, item.currency) }}
            </template>
            <template #payment_method="{ item }">
                <span class="capitalize">{{ item.payment_method?.replace(/_/g, ' ') || '---' }}</span>
            </template>
            <template #transaction_reference="{ item }">
                <span class="font-mono text-xs">{{ item.transaction_reference || '---' }}</span>
            </template>
            <template #supporting_document="{ item }">
                <Button
                    v-if="item.supporting_document_path"
                    as-child
                    variant="ghost"
                    size="sm"
                >
                    <a
                        :href="`/admin/finance/payments/${item.id}/proof`"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <FileText class="mr-1 h-4 w-4" />
                        View Proof
                    </a>
                </Button>
                <span v-else>---</span>
            </template>
            <template #status="{ item }">
                <StatusBadge :status="item.status" type="payment" />
            </template>
            <template #confirmed_at="{ item }">
                {{ item.confirmed_at ? formatDate(item.confirmed_at) : '---' }}
            </template>
            <template #actions="{ item }">
                <div v-if="item.status === 'pending'" class="flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        class="text-green-600 hover:text-green-700"
                        @click="confirmingPaymentId = item.id"
                    >
                        <CheckCircle class="mr-1 h-4 w-4" />
                        Confirm
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="text-destructive hover:text-destructive"
                        @click="rejectingPaymentId = item.id"
                    >
                        <XCircle class="mr-1 h-4 w-4" />
                        Reject
                    </Button>
                </div>
            </template>
        </DataTable>

        <!-- Confirm Dialog -->
        <ConfirmDialog
            :open="confirmingPaymentId !== null"
            title="Confirm Payment"
            description="Are you sure you want to confirm this payment? This will mark the associated invoice as paid."
            confirm-label="Confirm Payment"
            @confirm="confirmPayment"
            @cancel="confirmingPaymentId = null"
        />

        <!-- Reject Dialog -->
        <ConfirmDialog
            :open="rejectingPaymentId !== null"
            title="Reject Payment"
            description="Are you sure you want to reject this payment? The partner will be notified."
            confirm-label="Reject Payment"
            variant="destructive"
            @confirm="rejectPayment"
            @cancel="rejectingPaymentId = null"
        />
    </div>
</template>
