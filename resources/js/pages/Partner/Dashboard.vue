<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    LayoutDashboard,
    CalendarDays,
    CreditCard,
    ClipboardCheck,
    ArrowRight,
    FileText,
    Clock,
    AlertCircle,
    ClipboardList,
} from 'lucide-vue-next';
import { computed } from 'vue';
import StatusTracker from '@/components/partner/StatusTracker.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
    CardFooter,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import PartnerLayout from '@/layouts/PartnerLayout.vue';
import {
    canAccessEoi,
    getEoiActionLabel as resolveEoiActionLabel,
    getEoiDescription as resolveEoiDescription,
    getQuickActionSpecs,
} from '@/lib/partner-workflow.js';
import { create as createEoi } from '@/routes/partner/eoi';
import type {
    Partner,
    Invoice,
    ConferenceSession,
    ChangeRequest,
} from '@/types/partner';

defineOptions({ layout: PartnerLayout });

type BoothRef = {
    id: number;
    zone: string;
    booth_number: string;
    size: string;
    status: 'assigned' | 'reserved';
};

const props = defineProps<{
    partner: Partner | null;
    recentInvoices: Invoice[];
    sessions: ConferenceSession[];
    changeRequests: ChangeRequest[];
    booths?: BoothRef[];
}>();

const sessionsCount = computed(
    () => props.partner?.sessions?.length ?? props.sessions.length,
);

const paymentStatus = computed(() => {
    const unpaid = props.recentInvoices.filter(
        (i) => i.status !== 'paid' && i.status !== 'cancelled',
    );

    if (unpaid.length === 0) {
        return 'All Paid';
    }

    const overdue = unpaid.filter((i) => i.status === 'overdue');

    if (overdue.length > 0) {
        return `${overdue.length} Overdue`;
    }

    return `${unpaid.length} Pending`;
});

const onboardingPercent = computed(() => {
    const p = props.partner?.onboarding_progress;

    if (!p) {
        return 0;
    }

    return Math.round(
        (p.organization + p.sessions + p.communications + p.contacts) / 4,
    );
});

const pendingChanges = computed(
    () => props.changeRequests.filter((cr) => cr.status === 'pending').length,
);

const activePackage = computed(() => props.partner?.packages?.[0] ?? null);

const hasEoi = computed(() => !!props.partner);

const canOpenEoi = computed(() => {
    return canAccessEoi(props.partner?.status ?? null);
});

const eoiActionLabel = computed(() => {
    return resolveEoiActionLabel(props.partner?.status ?? null);
});

const eoiDescription = computed(() => {
    return resolveEoiDescription(props.partner?.status ?? null);
});

const eoiDateLabel = computed(() => {
    if (
        props.partner?.status === 'interest_submitted' &&
        props.partner?.submitted_at
    ) {
        return 'Submitted';
    }

    return 'Last updated';
});

const eoiDateValue = computed(() => {
    const partner = props.partner;

    if (!partner) {
        return null;
    }

    if (partner.status === 'interest_submitted' && partner.submitted_at) {
        return partner.submitted_at;
    }

    return partner.updated_at ?? partner.created_at;
});

const quickActions = computed(() => {
    const iconMap: Record<string, typeof ArrowRight> = {
        start_eoi: ArrowRight,
        draft_eoi: ArrowRight,
        edit_eoi: ArrowRight,
        revise_eoi: ArrowRight,
        commitment: FileText,
        payment: CreditCard,
        onboarding: ClipboardCheck,
        schedule: CalendarDays,
        invoices: FileText,
    } as const;

    return getQuickActionSpecs(
        props.partner?.status ?? null,
        Boolean(props.partner),
    ).map((action) => ({
        ...action,
        icon: iconMap[action.key],
    }));
});

function formatCurrency(amount: number, currency: string) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency,
    }).format(amount);
}

function formatDate(date: string) {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}
</script>

<template>
    <div class="space-y-8">
        <div>
            <h1 class="font-heading text-3xl font-bold tracking-tight">
                Dashboard
            </h1>
            <p class="mt-1 text-muted-foreground">
                {{
                    partner
                        ? `Welcome back, ${partner.organization_name}`
                        : 'Welcome! Get started by expressing interest in a partnership package.'
                }}
            </p>
        </div>

        <StatusTracker
            v-if="partner && partner.status !== 'rejected'"
            :current-status="partner.status"
        />

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-sm font-medium">Sessions</CardTitle>
                    <CalendarDays class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ sessionsCount }}</div>
                    <p class="text-xs text-muted-foreground">
                        Conference sessions
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-sm font-medium"
                        >Payment Status</CardTitle
                    >
                    <CreditCard class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ paymentStatus }}</div>
                    <p class="text-xs text-muted-foreground">Invoice status</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-sm font-medium"
                        >Onboarding</CardTitle
                    >
                    <ClipboardCheck class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">
                        {{ onboardingPercent }}%
                    </div>
                    <div class="mt-2 h-2 w-full rounded-full bg-secondary">
                        <div
                            class="h-2 rounded-full bg-primary transition-all"
                            :style="{ width: onboardingPercent + '%' }"
                        />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-sm font-medium">Status</CardTitle>
                    <LayoutDashboard class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <StatusBadge v-if="partner" :status="partner.status" />
                    <span v-else class="text-sm text-muted-foreground"
                        >Not started</span
                    >
                    <p class="mt-1 text-xs text-muted-foreground">
                        Current phase
                    </p>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle>Expression of Interest</CardTitle>
                    <CardDescription
                        >View your current EOI, whether it is still in draft or
                        already submitted.</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-6">
                    <div v-if="hasEoi" class="space-y-6">
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <ClipboardList
                                        class="h-4 w-4 text-muted-foreground"
                                    />
                                    <p class="text-sm font-medium">
                                        {{
                                            partner?.organization_name ||
                                            'Expression of Interest'
                                        }}
                                    </p>
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    {{ eoiDescription }}
                                </p>
                            </div>

                            <StatusBadge
                                v-if="partner"
                                :status="partner.status"
                            />
                        </div>

                        <div
                            class="grid gap-4 rounded-lg border border-border/60 p-4 sm:grid-cols-3"
                        >
                            <div class="space-y-1">
                                <p
                                    class="text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    Package
                                </p>
                                <p class="text-sm font-medium">
                                    {{
                                        activePackage?.name ??
                                        'Not selected yet'
                                    }}
                                </p>
                            </div>

                            <div class="space-y-1">
                                <p
                                    class="text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    Status
                                </p>
                                <div>
                                    <StatusBadge
                                        v-if="partner"
                                        :status="partner.status"
                                    />
                                </div>
                            </div>

                            <div class="space-y-1">
                                <p
                                    class="text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    {{ eoiDateLabel }}
                                </p>
                                <p class="text-sm font-medium">
                                    {{
                                        eoiDateValue
                                            ? formatDate(eoiDateValue)
                                            : 'Not available'
                                    }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <p class="text-sm text-muted-foreground">
                                {{ partner?.email }}
                            </p>

                            <Link v-if="canOpenEoi" :href="createEoi()">
                                <Button variant="outline">
                                    <ClipboardList class="mr-2 h-4 w-4" />
                                    {{ eoiActionLabel }}
                                </Button>
                            </Link>
                        </div>
                    </div>

                    <div v-else class="space-y-4 py-2">
                        <p class="text-sm text-muted-foreground">
                            You do not have a draft or submitted expression of
                            interest yet.
                        </p>

                        <Link :href="createEoi()">
                            <Button>
                                <ClipboardList class="mr-2 h-4 w-4" />
                                Start Expression of Interest
                            </Button>
                        </Link>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Quick Actions</CardTitle>
                    <CardDescription
                        >Common tasks based on your current
                        status</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-2">
                    <Link
                        v-for="action in quickActions"
                        :key="action.label"
                        :href="action.href"
                    >
                        <Button
                            variant="outline"
                            class="w-full justify-between"
                        >
                            <span class="flex items-center gap-2">
                                <component :is="action.icon" class="h-4 w-4" />
                                {{ action.label }}
                            </span>
                            <ArrowRight class="h-4 w-4" />
                        </Button>
                    </Link>
                </CardContent>
                <CardFooter v-if="pendingChanges > 0">
                    <div class="flex items-center gap-2 text-sm">
                        <Clock class="h-4 w-4 text-muted-foreground" />
                        <span class="text-muted-foreground"
                            >{{ pendingChanges }} pending change
                            request(s)</span
                        >
                    </div>
                </CardFooter>
            </Card>
        </div>

        <Card v-if="booths && booths.length">
            <CardHeader>
                <CardTitle>Exhibition Booth</CardTitle>
                <CardDescription>
                    Your assigned booth(s) in the AHAIC exhibition floor plan.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="booth in booths"
                        :key="booth.id"
                        class="rounded-lg border-2 p-4"
                        :class="booth.status === 'assigned' ? 'border-primary bg-primary/5' : 'border-amber-400 bg-amber-50 dark:bg-amber-950/40'"
                    >
                        <div class="text-muted-foreground text-xs uppercase tracking-wide">{{ booth.zone }}</div>
                        <div class="mt-1 flex items-baseline gap-2">
                            <span class="text-2xl font-semibold">#{{ booth.booth_number }}</span>
                            <span class="text-muted-foreground text-xs">{{ booth.size }} m</span>
                        </div>
                        <div class="text-muted-foreground mt-1 text-xs capitalize">
                            Status: {{ booth.status }}
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Recent Activity</CardTitle>
                <CardDescription
                    >Latest updates across your sessions and
                    invoices</CardDescription
                >
            </CardHeader>
            <CardContent class="space-y-4">
                <div
                    v-if="
                        recentInvoices.length === 0 &&
                        sessions.length === 0 &&
                        changeRequests.length === 0
                    "
                    class="py-8 text-center text-sm text-muted-foreground"
                >
                    No recent activity to display.
                </div>

                <div
                    v-for="invoice in recentInvoices.slice(0, 3)"
                    :key="'inv-' + invoice.id"
                    class="flex items-center gap-4"
                >
                    <div
                        class="flex h-9 w-9 items-center justify-center rounded-full bg-muted"
                    >
                        <FileText class="h-4 w-4" />
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium">
                            Invoice {{ invoice.invoice_number }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ formatDate(invoice.date_of_service) }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-medium">
                            {{
                                formatCurrency(invoice.amount, invoice.currency)
                            }}
                        </p>
                        <StatusBadge :status="invoice.status" />
                    </div>
                </div>

                <Separator
                    v-if="recentInvoices.length > 0 && sessions.length > 0"
                />

                <div
                    v-for="session in sessions.slice(0, 3)"
                    :key="'ses-' + session.id"
                    class="flex items-center gap-4"
                >
                    <div
                        class="flex h-9 w-9 items-center justify-center rounded-full bg-muted"
                    >
                        <CalendarDays class="h-4 w-4" />
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium">{{ session.title }}</p>
                        <p class="text-xs text-muted-foreground capitalize">
                            {{ session.format }}
                        </p>
                    </div>
                    <StatusBadge :status="session.status" />
                </div>

                <Separator
                    v-if="
                        changeRequests.length > 0 &&
                        (recentInvoices.length > 0 || sessions.length > 0)
                    "
                />

                <div
                    v-for="cr in changeRequests.slice(0, 3)"
                    :key="'cr-' + cr.id"
                    class="flex items-center gap-4"
                >
                    <div
                        class="flex h-9 w-9 items-center justify-center rounded-full bg-muted"
                    >
                        <AlertCircle class="h-4 w-4" />
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium">
                            Change Request: {{ cr.type }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{
                                cr.session?.title ??
                                'Session #' + cr.conference_session_id
                            }}
                        </p>
                    </div>
                    <StatusBadge :status="cr.status" />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
