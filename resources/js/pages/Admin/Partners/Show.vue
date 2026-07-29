<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    Mail,
    Phone,
    User,
    CreditCard,
    CalendarDays,
    Users,
    ArrowLeftRight,
    Clock,
    CheckCircle2,
    Circle,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import DataTable from '@/components/shared/DataTable.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import {
    Select,
    SelectTrigger,
    SelectValue,
    SelectContent,
    SelectItem,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type {
    Partner,
    ConferenceSession,
    Invoice,
    Payment,
    PartnerContact,
    ChangeRequest,
} from '@/types/partner';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    partner: Partner & {
        sessions?: ConferenceSession[];
        invoices?: (Invoice & { payments?: Payment[] })[];
        contacts?: PartnerContact[];
        change_requests?: ChangeRequest[];
        audit_log?: {
            id: number;
            description: string;
            event: string;
            causer_name: string | null;
            created_at: string;
        }[];
    };
}>();

const activeTab = ref('overview');

const tabs = [
    { key: 'overview', label: 'Overview', icon: Building2 },
    { key: 'sessions', label: 'Sessions', icon: CalendarDays },
    { key: 'invoices', label: 'Invoices & Payments', icon: CreditCard },
    { key: 'onboarding', label: 'Onboarding', icon: CheckCircle2 },
    { key: 'contacts', label: 'Contacts', icon: Users },
    { key: 'changes', label: 'Change Requests', icon: ArrowLeftRight },
    { key: 'audit', label: 'Audit Log', icon: Clock },
];

const statusForm = useForm({
    status: props.partner.status as string,
});

const decisionForm = useForm({
    status: props.partner.status as string,
});

function updateStatus(
    newStatus: string | number | bigint | Record<string, any> | null,
) {
    if (typeof newStatus !== 'string') {
        return;
    }

    statusForm.status = newStatus;
    statusForm.patch(`/admin/partners/${props.partner.id}/status`);
}

function applyDecision(status: 'pending_agreement' | 'rejected') {
    decisionForm.status = status;
    decisionForm.put(`/admin/partners/${props.partner.id}/status`);
}

const onboardingSteps = computed(() => {
    const progress = props.partner.onboarding_progress;

    if (!progress) {
        return [];
    }

    return [
        { label: 'Organization Profile', value: progress.organization },
        { label: 'Sessions', value: progress.sessions },
        { label: 'Communications', value: progress.communications },
        { label: 'Contacts', value: progress.contacts },
    ];
});

function formatDate(dateStr: string | null): string {
    if (!dateStr) {
        return '---';
    }

    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatCurrency(amount: number, currency: string): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency,
        minimumFractionDigits: 0,
    }).format(amount);
}

const sessionColumns = [
    { key: 'title', label: 'Title' },
    { key: 'format', label: 'Format' },
    { key: 'expected_participants', label: 'Expected' },
    { key: 'status', label: 'Status' },
];

const invoiceColumns = [
    { key: 'invoice_number', label: 'Invoice #' },
    { key: 'amount', label: 'Amount' },
    { key: 'due_date', label: 'Due Date' },
    { key: 'status', label: 'Status' },
];

const contactColumns = [
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Email' },
    { key: 'phone', label: 'Phone' },
    { key: 'role', label: 'Role' },
    { key: 'organization', label: 'Organization' },
];

const changeColumns = [
    { key: 'type', label: 'Type' },
    { key: 'reason', label: 'Reason' },
    { key: 'status', label: 'Status' },
    { key: 'created_at', label: 'Date' },
];
</script>

<template>
    <Head :title="`Partner: ${partner.organization_name}`" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-start justify-between">
            <div>
                <Link
                    href="/admin/partners"
                    class="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Back to Partners
                </Link>
                <h1 class="font-heading text-2xl font-bold">
                    {{ partner.organization_name }}
                </h1>
                <div
                    class="mt-1 flex items-center gap-3 text-sm text-muted-foreground"
                >
                    <span class="flex items-center gap-1">
                        <User class="h-3 w-3" />
                        {{ partner.contact_person }}
                    </span>
                    <span class="flex items-center gap-1">
                        <Mail class="h-3 w-3" />
                        {{ partner.email }}
                    </span>
                    <span v-if="partner.phone" class="flex items-center gap-1">
                        <Phone class="h-3 w-3" />
                        {{ partner.phone }}
                    </span>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <Button
                    v-if="partner.status === 'interest_submitted'"
                    variant="outline"
                    :disabled="decisionForm.processing"
                    @click="applyDecision('rejected')"
                >
                    Reject Submission
                </Button>
                <Button
                    v-if="partner.status === 'interest_submitted'"
                    :disabled="decisionForm.processing"
                    @click="applyDecision('pending_agreement')"
                >
                    Approve Submission
                </Button>
                <StatusBadge :status="partner.status" type="partner" />
                <Select
                    :model-value="partner.status"
                    @update:model-value="updateStatus"
                >
<!--                    <SelectTrigger class="w-[180px]">-->
<!--                        <SelectValue placeholder="Change Status" />-->
<!--                    </SelectTrigger>-->
<!--                    <SelectContent>-->
<!--                        <SelectItem value="interest_submitted"-->
<!--                            >Interest Submitted</SelectItem-->
<!--                        >-->
<!--                        <SelectItem value="rejected">Rejected</SelectItem>-->
<!--                        <SelectItem value="pending_agreement"-->
<!--                            >Pending Agreement</SelectItem-->
<!--                        >-->
<!--                        <SelectItem value="pending_payment"-->
<!--                            >Pending Payment</SelectItem-->
<!--                        >-->
<!--                        <SelectItem value="confirmed">Confirmed</SelectItem>-->
<!--                        <SelectItem value="onboarding">Onboarding</SelectItem>-->
<!--                        <SelectItem value="submitted">Submitted</SelectItem>-->
<!--                        <SelectItem value="scheduled">Scheduled</SelectItem>-->
<!--                        <SelectItem value="finalized">Finalized</SelectItem>-->
<!--                    </SelectContent>-->
                </Select>
            </div>
        </div>

        <!-- Timeline -->
        <Card>
            <CardContent class="pt-6">
                <div class="flex items-center gap-2 overflow-x-auto text-xs">
                    <div
                        class="flex items-center gap-1 rounded-full bg-muted px-3 py-1 whitespace-nowrap"
                    >
                        <Circle class="h-3 w-3 text-muted-foreground" />
                        Created {{ formatDate(partner.created_at) }}
                    </div>
                    <div
                        v-if="partner.submitted_at"
                        class="flex items-center gap-1 rounded-full bg-primary/10 px-3 py-1 whitespace-nowrap text-primary"
                    >
                        <CheckCircle2 class="h-3 w-3" />
                        Submitted {{ formatDate(partner.submitted_at) }}
                    </div>
                    <div
                        v-if="partner.confirmed_at"
                        class="flex items-center gap-1 rounded-full bg-green-100 px-3 py-1 whitespace-nowrap text-green-700"
                    >
                        <CheckCircle2 class="h-3 w-3" />
                        Confirmed {{ formatDate(partner.confirmed_at) }}
                    </div>
                    <div
                        v-if="partner.locked_at"
                        class="flex items-center gap-1 rounded-full bg-indigo-100 px-3 py-1 whitespace-nowrap text-indigo-700"
                    >
                        <CheckCircle2 class="h-3 w-3" />
                        Locked {{ formatDate(partner.locked_at) }}
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Tab Navigation -->
        <div class="flex gap-1 overflow-x-auto border-b">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                class="flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors"
                :class="
                    activeTab === tab.key
                        ? 'border-primary text-primary'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="activeTab = tab.key"
            >
                <component :is="tab.icon" class="h-4 w-4" />
                {{ tab.label }}
            </button>
        </div>

        <!-- Overview Tab -->
        <div v-if="activeTab === 'overview'" class="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Organization Details</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground"
                            >Physical Address</span
                        >
                        <span>{{ partner.physical_address || '---' }}</span>
                    </div>
                    <Separator />
                    <div class="flex justify-between">
                        <span class="text-muted-foreground"
                            >Billing Address</span
                        >
                        <span>{{ partner.billing_address || '---' }}</span>
                    </div>
                    <Separator />
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Tax Details</span>
                        <span>{{ partner.tax_details || '---' }}</span>
                    </div>
                    <Separator />
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Customer Code</span>
                        <span>{{ partner.customer_code || '---' }}</span>
                    </div>
                    <Separator />
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Participants</span>
                        <span>{{
                            partner.number_of_participants ?? '---'
                        }}</span>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Package Information</CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="partner.packages && partner.packages.length > 0"
                        class="space-y-3 text-sm"
                    >
                        <div
                            v-for="pkg in partner.packages"
                            :key="pkg.id"
                            class="rounded-lg border p-4"
                        >
                            <div class="flex items-center justify-between">
                                <span class="font-medium">{{ pkg.name }}</span>
                                <Badge variant="outline" class="capitalize">{{
                                    pkg.tier
                                }}</Badge>
                            </div>
                            <p class="mt-1 text-muted-foreground">
                                {{ formatCurrency(pkg.price, pkg.currency) }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ pkg.session_slots }} session slot{{
                                    pkg.session_slots !== 1 ? 's' : ''
                                }}
                                <span v-if="pkg.exhibition_space">
                                    &middot;
                                    {{ pkg.exhibition_space }} exhibition</span
                                >
                            </p>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        No package assigned.
                    </p>
                </CardContent>
            </Card>

            <Card v-if="partner.description" class="lg:col-span-2">
                <CardHeader>
                    <CardTitle>Description</CardTitle>
                </CardHeader>
                <CardContent>
                    <p
                        class="text-sm whitespace-pre-line text-muted-foreground"
                    >
                        {{ partner.description }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Sessions Tab -->
        <div v-if="activeTab === 'sessions'">
            <DataTable
                :columns="sessionColumns"
                :data="partner.sessions || []"
                empty-message="No sessions submitted."
            >
                <template #format="{ item }">
                    <Badge variant="outline" class="capitalize">{{
                        item.format
                    }}</Badge>
                </template>
                <template #status="{ item }">
                    <StatusBadge :status="item.status" type="session" />
                </template>
            </DataTable>
        </div>

        <!-- Invoices Tab -->
        <div v-if="activeTab === 'invoices'">
            <DataTable
                :columns="invoiceColumns"
                :data="partner.invoices || []"
                empty-message="No invoices found."
            >
                <template #amount="{ item }">
                    {{ formatCurrency(item.amount, item.currency) }}
                </template>
                <template #due_date="{ item }">
                    {{ formatDate(item.due_date) }}
                </template>
                <template #status="{ item }">
                    <StatusBadge :status="item.status" type="invoice" />
                </template>
            </DataTable>
        </div>

        <!-- Onboarding Tab -->
        <div v-if="activeTab === 'onboarding'">
            <Card>
                <CardHeader>
                    <CardTitle>Onboarding Progress</CardTitle>
                    <CardDescription
                        >Track this partner's progress through the onboarding
                        process.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <div v-if="onboardingSteps.length > 0" class="space-y-4">
                        <div
                            v-for="step in onboardingSteps"
                            :key="step.label"
                            class="space-y-1"
                        >
                            <div
                                class="flex items-center justify-between text-sm"
                            >
                                <span>{{ step.label }}</span>
                                <span class="font-medium"
                                    >{{ step.value }}%</span
                                >
                            </div>
                            <div
                                class="h-2 w-full overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="
                                        step.value === 100
                                            ? 'bg-green-500'
                                            : 'bg-primary'
                                    "
                                    :style="{ width: `${step.value}%` }"
                                />
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        No onboarding data available.
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Contacts Tab -->
        <div v-if="activeTab === 'contacts'">
            <DataTable
                :columns="contactColumns"
                :data="partner.contacts || []"
                empty-message="No contacts found."
            />
        </div>

        <!-- Change Requests Tab -->
        <div v-if="activeTab === 'changes'">
            <DataTable
                :columns="changeColumns"
                :data="partner.change_requests || []"
                empty-message="No change requests."
            >
                <template #type="{ item }">
                    <Badge variant="outline" class="capitalize">{{
                        item.type
                    }}</Badge>
                </template>
                <template #status="{ item }">
                    <StatusBadge :status="item.status" type="change_request" />
                </template>
                <template #created_at="{ item }">
                    {{ formatDate(item.created_at) }}
                </template>
            </DataTable>
        </div>

        <!-- Audit Log Tab -->
        <div v-if="activeTab === 'audit'">
            <Card>
                <CardContent class="pt-6">
                    <div
                        v-if="partner.audit_log && partner.audit_log.length > 0"
                        class="space-y-4"
                    >
                        <div
                            v-for="entry in partner.audit_log"
                            :key="entry.id"
                            class="flex items-start gap-3 border-l-2 border-muted pl-4"
                        >
                            <div class="flex-1 text-sm">
                                <p>{{ entry.description }}</p>
                                <p class="text-xs text-muted-foreground">
                                    <span v-if="entry.causer_name"
                                        >{{ entry.causer_name }} &middot;
                                    </span>
                                    {{ formatDate(entry.created_at) }}
                                </p>
                            </div>
                            <Badge variant="outline" class="text-xs capitalize">
                                {{ entry.event }}
                            </Badge>
                        </div>
                    </div>
                    <p v-else class="text-center text-sm text-muted-foreground">
                        No audit log entries.
                    </p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
