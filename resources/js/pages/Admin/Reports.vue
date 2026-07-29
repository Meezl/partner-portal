<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { BarChart3, Users, Calendar, DollarSign, Download, Star } from 'lucide-vue-next';
import StatsCard from '@/components/admin/StatsCard.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import AdminLayout from '@/layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    stats: {
        totalPartners: number;
        totalSessions: number;
        sessionsDelivered: number;
        totalRevenue: number;
        averageSatisfaction: number;
        resourceUtilization: number;
    };
}>();

const generateReport = () => {
    router.get('/admin/reports/generate', { type: 'full' });
};
</script>

<template>
    <Head title="Reports" />

    <div class="space-y-6 p-6">
        <div class="flex items-center justify-between">
            <h1 class="font-heading text-2xl">Conference Reports & Analytics</h1>
            <Button @click="generateReport">
                <Download class="mr-2 h-4 w-4" /> Generate Full Report
            </Button>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <StatsCard title="Total Partners" :value="stats.totalPartners" :icon="Users" />
            <StatsCard title="Sessions Delivered" :value="`${stats.sessionsDelivered} / ${stats.totalSessions}`" :icon="Calendar" />
            <StatsCard title="Total Revenue" :value="`$${Number(stats.totalRevenue).toLocaleString()}`" :icon="DollarSign" />
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Partner Satisfaction</CardTitle>
                    <CardDescription>Average rating from feedback surveys</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center gap-4">
                        <div class="text-4xl font-heading text-primary">{{ stats.averageSatisfaction.toFixed(1) }}</div>
                        <div class="flex gap-1">
                            <Star v-for="i in 5" :key="i" class="h-5 w-5" :class="i <= Math.round(stats.averageSatisfaction) ? 'fill-ahaic-yellow text-ahaic-yellow' : 'text-gray-300'" />
                        </div>
                        <span class="text-sm text-muted-foreground">out of 5</span>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Resource Utilization</CardTitle>
                    <CardDescription>Room and time slot usage across the conference</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-3">
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span>Room Utilization</span>
                                <span>{{ stats.resourceUtilization }}%</span>
                            </div>
                            <div class="h-2 w-full rounded-full bg-muted">
                                <div class="h-2 rounded-full bg-primary" :style="{ width: `${stats.resourceUtilization}%` }"></div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Report Types</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid gap-3 md:grid-cols-2">
                    <div class="flex items-center justify-between rounded-lg border p-4">
                        <div>
                            <p class="font-medium">Session Delivery Report</p>
                            <p class="text-sm text-muted-foreground">All sessions with attendance and status</p>
                        </div>
                        <Button variant="outline" size="sm">Generate</Button>
                    </div>
                    <div class="flex items-center justify-between rounded-lg border p-4">
                        <div>
                            <p class="font-medium">Financial Summary</p>
                            <p class="text-sm text-muted-foreground">Revenue, payments, and outstanding invoices</p>
                        </div>
                        <Button variant="outline" size="sm">Generate</Button>
                    </div>
                    <div class="flex items-center justify-between rounded-lg border p-4">
                        <div>
                            <p class="font-medium">Partner Feedback Summary</p>
                            <p class="text-sm text-muted-foreground">Aggregated satisfaction and comments</p>
                        </div>
                        <Button variant="outline" size="sm">Generate</Button>
                    </div>
                    <div class="flex items-center justify-between rounded-lg border p-4">
                        <div>
                            <p class="font-medium">Conflict Resolution Log</p>
                            <p class="text-sm text-muted-foreground">All scheduling conflicts and resolutions</p>
                        </div>
                        <Button variant="outline" size="sm">Generate</Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
