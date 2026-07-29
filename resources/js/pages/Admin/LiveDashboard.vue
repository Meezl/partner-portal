<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Radio, Clock, CheckCircle, PlayCircle } from 'lucide-vue-next';
import StatsCard from '@/components/admin/StatsCard.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    sessions: Array<{
        id: number;
        title: string;
        room: string;
        time: string;
        status: 'upcoming' | 'in_progress' | 'completed';
        partner: string;
    }>;
    roomUsage: Record<string, { occupied: boolean; session?: string }>;
    stats: {
        currentlyRunning: number;
        upcoming: number;
        completed: number;
    };
}>();

const statusIcon: Record<string, typeof Radio> = {
    in_progress: PlayCircle,
    upcoming: Clock,
    completed: CheckCircle,
};

const statusColor: Record<string, string> = {
    in_progress: 'bg-green-100 text-green-800',
    upcoming: 'bg-blue-100 text-blue-800',
    completed: 'bg-gray-100 text-gray-800',
};
</script>

<template>
    <Head title="Live Dashboard" />

    <div class="space-y-6 p-6">
        <div class="flex items-center gap-3">
            <Radio class="h-5 w-5 text-red-500 animate-pulse" />
            <h1 class="font-heading text-2xl">Live Conference Dashboard</h1>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <StatsCard title="Currently Running" :value="stats.currentlyRunning" :icon="PlayCircle" />
            <StatsCard title="Upcoming" :value="stats.upcoming" :icon="Clock" />
            <StatsCard title="Completed" :value="stats.completed" :icon="CheckCircle" />
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Session Status</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="space-y-3">
                    <div v-for="session in sessions" :key="session.id" class="flex items-center justify-between rounded-lg border p-3">
                        <div>
                            <p class="font-medium">{{ session.title }}</p>
                            <p class="text-sm text-muted-foreground">{{ session.room }} &middot; {{ session.time }} &middot; {{ session.partner }}</p>
                        </div>
                        <Badge :class="statusColor[session.status]">{{ session.status.replace('_', ' ') }}</Badge>
                    </div>
                    <p v-if="sessions.length === 0" class="text-center text-muted-foreground py-8">No sessions scheduled for today</p>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Room Usage</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid gap-3 md:grid-cols-3">
                    <div v-for="(info, room) in roomUsage" :key="room" class="rounded-lg border p-3">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">{{ room }}</span>
                            <span :class="info.occupied ? 'h-3 w-3 rounded-full bg-red-500' : 'h-3 w-3 rounded-full bg-green-500'"></span>
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">{{ info.occupied ? info.session : 'Available' }}</p>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
