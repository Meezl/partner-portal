<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Users,
    CreditCard,
    CalendarDays,
    AlertTriangle,
    DollarSign,
    ArrowRight,
    Clock,
} from 'lucide-vue-next';
import StatsCard from '@/components/admin/StatsCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { PartnerStatus } from '@/types/partner';

defineOptions({ layout: AdminLayout });

defineProps<{
    stats: {
        totalPartners: number;
        pendingPayments: number;
        scheduledSessions: number;
        unresolvedConflicts: number;
        totalRevenue: number;
        partnersByStatus: Record<PartnerStatus, number>;
    };
    recentActivity: {
        id: number;
        description: string;
        causer_name: string | null;
        created_at: string;
        event: string;
    }[];
}>();

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 0,
    }).format(amount);
}

function formatTimeAgo(dateStr: string): string {
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);

    if (diffMins < 60) {
return `${diffMins}m ago`;
}

    const diffHours = Math.floor(diffMins / 60);

    if (diffHours < 24) {
return `${diffHours}h ago`;
}

    const diffDays = Math.floor(diffHours / 24);

    return `${diffDays}d ago`;
}

const statusOrder: PartnerStatus[] = [
    'interest_submitted',
    'pending_agreement',
    'pending_payment',
    'confirmed',
    'onboarding',
    'submitted',
    'scheduled',
    'finalized',
];

const statusBarColors: Record<string, string> = {
    interest_submitted: 'bg-blue-400',
    pending_agreement: 'bg-yellow-400',
    pending_payment: 'bg-orange-400',
    confirmed: 'bg-green-400',
    onboarding: 'bg-purple-400',
    submitted: 'bg-teal-400',
    scheduled: 'bg-indigo-400',
    finalized: 'bg-emerald-600',
    draft: 'bg-gray-300',
};

const quickLinks = [
    { title: 'Partners', href: '/admin/partners', icon: Users },
    { title: 'Payments', href: '/admin/finance/payments', icon: CreditCard },
    { title: 'Schedule Board', href: '/admin/scheduling', icon: CalendarDays },
    { title: 'Conflicts', href: '/admin/scheduling/conflicts', icon: AlertTriangle },
];
</script>

<template>
    <Head title="Admin Dashboard" />

    <div class="space-y-6">
        <h1 class="font-heading text-2xl font-bold">Dashboard</h1>

        <!-- Stats Grid -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatsCard
                title="Total Partners"
                :value="stats.totalPartners"
                :icon="Users"
                variant="primary"
            />
            <StatsCard
                title="Pending Payments"
                :value="stats.pendingPayments"
                :icon="CreditCard"
                variant="warning"
            />
            <StatsCard
                title="Scheduled Sessions"
                :value="stats.scheduledSessions"
                :icon="CalendarDays"
                variant="accent"
            />
            <StatsCard
                title="Unresolved Conflicts"
                :value="stats.unresolvedConflicts"
                :icon="AlertTriangle"
                :variant="stats.unresolvedConflicts > 0 ? 'destructive' : 'default'"
            />
        </div>

        <!-- Revenue -->
        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium text-muted-foreground">Total Revenue</CardTitle>
                <DollarSign class="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
                <div class="text-3xl font-bold">{{ formatCurrency(stats.totalRevenue) }}</div>
            </CardContent>
        </Card>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Partner Status Breakdown -->
            <Card>
                <CardHeader>
                    <CardTitle>Partner Status Breakdown</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-3">
                        <div
                            v-for="status in statusOrder"
                            :key="status"
                            class="flex items-center gap-3"
                        >
                            <div class="w-32 text-sm text-muted-foreground capitalize">
                                {{ status.replace(/_/g, ' ') }}
                            </div>
                            <div class="flex-1">
                                <div class="h-5 w-full overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="h-full rounded-full transition-all"
                                        :class="statusBarColors[status] || 'bg-gray-300'"
                                        :style="{
                                            width: stats.totalPartners > 0
                                                ? `${((stats.partnersByStatus[status] || 0) / stats.totalPartners) * 100}%`
                                                : '0%',
                                        }"
                                    />
                                </div>
                            </div>
                            <div class="w-8 text-right text-sm font-medium">
                                {{ stats.partnersByStatus[status] || 0 }}
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Recent Activity -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Recent Activity</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="recentActivity.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                        No recent activity.
                    </div>
                    <div v-else class="space-y-4">
                        <div
                            v-for="entry in recentActivity.slice(0, 8)"
                            :key="entry.id"
                            class="flex items-start gap-3"
                        >
                            <div class="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-muted">
                                <Clock class="h-3 w-3 text-muted-foreground" />
                            </div>
                            <div class="flex-1 text-sm">
                                <p>{{ entry.description }}</p>
                                <p class="text-xs text-muted-foreground">
                                    <span v-if="entry.causer_name">{{ entry.causer_name }} &middot; </span>
                                    {{ formatTimeAgo(entry.created_at) }}
                                </p>
                            </div>
                            <Badge variant="outline" class="text-xs capitalize">
                                {{ entry.event }}
                            </Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Quick Links -->
        <Card>
            <CardHeader>
                <CardTitle>Quick Links</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Link
                        v-for="link in quickLinks"
                        :key="link.href"
                        :href="link.href"
                    >
                        <Button variant="outline" class="w-full justify-start gap-2">
                            <component :is="link.icon" class="h-4 w-4" />
                            {{ link.title }}
                            <ArrowRight class="ml-auto h-4 w-4" />
                        </Button>
                    </Link>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
