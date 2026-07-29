<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    Clock,
    MapPin,
    Users,
    ArrowRight,
    AlertCircle,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import PartnerLayout from '@/layouts/PartnerLayout.vue';
import type { Partner, SessionSchedule } from '@/types/partner';

defineOptions({ layout: PartnerLayout });

const props = defineProps<{
    partner: Partner;
    schedules: SessionSchedule[];
}>();

const groupedByDate = computed(() => {
    const groups: Record<string, SessionSchedule[]> = {};

    for (const schedule of props.schedules) {
        const date = schedule.time_slot?.date ?? 'Unscheduled';

        if (!groups[date]) {
groups[date] = [];
}

        groups[date].push(schedule);
    }

    // Sort dates
    const sorted: { date: string; schedules: SessionSchedule[] }[] = [];

    for (const date of Object.keys(groups).sort()) {
        sorted.push({
            date,
            schedules: groups[date].sort((a, b) => {
                const aTime = a.time_slot?.start_time ?? '';
                const bTime = b.time_slot?.start_time ?? '';

                return aTime.localeCompare(bTime);
            }),
        });
    }

    return sorted;
});

function formatDate(dateStr: string) {
    if (dateStr === 'Unscheduled') {
return dateStr;
}

    return new Date(dateStr).toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
}

function formatTime(time: string) {
    const [h, m] = time.split(':');
    const hour = parseInt(h);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const display = hour > 12 ? hour - 12 : hour === 0 ? 12 : hour;

    return `${display}:${m} ${ampm}`;
}
</script>

<template>
    <div class="space-y-8">
        <div>
            <h1 class="font-heading text-3xl font-bold tracking-tight">Schedule</h1>
            <p class="text-muted-foreground mt-1">View your scheduled sessions and their assigned rooms and times.</p>
        </div>

        <div v-if="schedules.length === 0">
            <Card>
                <CardContent class="flex flex-col items-center gap-4 py-12">
                    <CalendarDays class="text-muted-foreground h-12 w-12" />
                    <div class="text-center">
                        <p class="font-medium">No sessions scheduled yet</p>
                        <p class="text-muted-foreground mt-1 text-sm">Your sessions will appear here once the conference schedule is finalized.</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div v-else class="space-y-6">
            <div v-for="group in groupedByDate" :key="group.date">
                <h2 class="font-heading mb-3 text-lg font-semibold">{{ formatDate(group.date) }}</h2>
                <div class="space-y-3">
                    <Card v-for="schedule in group.schedules" :key="schedule.id">
                        <CardContent class="py-4">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1 space-y-2">
                                    <h3 class="font-medium">{{ schedule.session?.title ?? 'Untitled Session' }}</h3>
                                    <div class="flex flex-wrap items-center gap-4 text-sm">
                                        <span v-if="schedule.time_slot" class="text-muted-foreground flex items-center gap-1">
                                            <Clock class="h-4 w-4" />
                                            {{ formatTime(schedule.time_slot.start_time) }} - {{ formatTime(schedule.time_slot.end_time) }}
                                        </span>
                                        <span v-if="schedule.room" class="text-muted-foreground flex items-center gap-1">
                                            <MapPin class="h-4 w-4" />
                                            {{ schedule.room.name }}
                                            <template v-if="schedule.room.building"> ({{ schedule.room.building }})</template>
                                        </span>
                                        <span v-if="schedule.room" class="text-muted-foreground flex items-center gap-1">
                                            <Users class="h-4 w-4" />
                                            Capacity: {{ schedule.room.capacity }}
                                        </span>
                                    </div>

                                    <div v-if="schedule.resource_assignments && schedule.resource_assignments.length > 0" class="mt-2">
                                        <p class="text-muted-foreground text-xs font-medium">Assigned Resources:</p>
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            <Badge v-for="res in schedule.resource_assignments" :key="res.id" variant="outline" class="text-xs">
                                                {{ res.resource_type }}: {{ res.name ?? 'TBD' }}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-col items-end gap-2">
                                    <Badge :variant="schedule.status === 'confirmed' ? 'default' : 'secondary'" class="capitalize">
                                        {{ schedule.status }}
                                    </Badge>
                                    <Link :href="`/partner/change-requests/create?session=${schedule.conference_session_id}`">
                                        <Button variant="outline" size="sm">
                                            <AlertCircle class="mr-1 h-4 w-4" />
                                            Request Change
                                        </Button>
                                    </Link>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </div>
</template>
