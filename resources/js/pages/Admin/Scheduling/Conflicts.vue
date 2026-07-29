<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { AlertTriangle, Clock, MapPin, ArrowRight } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AdminLayout from '@/layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

interface ConflictSession {
    id: number;
    title: string;
    partner_name: string;
    format: string;
}

interface Conflict {
    id: number;
    type: 'room_double_booked' | 'partner_overlap' | 'resource_double_booked';
    description: string;
    sessions: ConflictSession[];
    time_slot: {
        date: string;
        start_time: string;
        end_time: string;
    };
    room?: {
        id: number;
        name: string;
    };
    suggested_resolution: string;
}

defineProps<{
    conflicts: Conflict[];
}>();

const typeLabels: Record<string, string> = {
    room_double_booking: 'Room Double-Booked',
    partner_overlap: 'Partner Overlap',
    resource_double_booked: 'Resource Double-Booked',
};

const typeColors: Record<string, string> = {
    room_double_booking: 'bg-red-100 text-red-800 border-red-300',
    partner_overlap: 'bg-orange-100 text-orange-800 border-orange-300',
    resource_double_booked: 'bg-yellow-100 text-yellow-800 border-yellow-300',
};

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    });
}

function formatTime(time: string): string {
    return time.substring(0, 5);
}
</script>

<template>
    <Head title="Scheduling Conflicts" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="font-heading text-2xl font-bold">Scheduling Conflicts</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Review and resolve detected scheduling conflicts.
                </p>
            </div>
            <Badge
                :variant="conflicts.length > 0 ? 'destructive' : 'default'"
            >
                <AlertTriangle class="mr-1 h-3 w-3" />
                {{ conflicts.length }} conflict{{ conflicts.length !== 1 ? 's' : '' }}
            </Badge>
        </div>

        <div v-if="conflicts.length === 0" class="rounded-lg border bg-card p-12 text-center">
            <AlertTriangle class="mx-auto mb-3 h-12 w-12 text-green-500" />
            <h3 class="text-lg font-medium">No Conflicts Detected</h3>
            <p class="mt-1 text-sm text-muted-foreground">
                All sessions are scheduled without any overlapping conflicts.
            </p>
        </div>

        <div v-else class="space-y-4">
            <Card v-for="conflict in conflicts" :key="conflict.id" class="border-l-4 border-l-destructive">
                <CardHeader>
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <Badge variant="outline" :class="typeColors[conflict.type]">
                                    {{ typeLabels[conflict.type] || conflict.type }}
                                </Badge>
                            </div>
                            <CardTitle class="mt-2 text-base">{{ conflict.description }}</CardTitle>
                        </div>
                        <div class="text-right text-xs text-muted-foreground">
                            <div class="flex items-center gap-1">
                                <Clock class="h-3 w-3" />
                                {{ formatDate(conflict.time_slot.date) }}
                                {{ formatTime(conflict.time_slot.start_time) }}-{{ formatTime(conflict.time_slot.end_time) }}
                            </div>
                            <div v-if="conflict.room" class="mt-1 flex items-center gap-1">
                                <MapPin class="h-3 w-3" />
                                {{ conflict.room.name }}
                            </div>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <!-- Involved Sessions -->
                        <div>
                            <h4 class="mb-2 text-sm font-medium text-muted-foreground">Involved Sessions</h4>
                            <div class="space-y-2">
                                <div
                                    v-for="session in conflict.sessions"
                                    :key="session.id"
                                    class="flex items-center justify-between rounded-lg border bg-muted/50 p-3"
                                >
                                    <div>
                                        <p class="text-sm font-medium">{{ session.title }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ session.partner_name }} &middot;
                                            <span class="capitalize">{{ session.format }}</span>
                                        </p>
                                    </div>
                                    <Button variant="ghost" size="sm">
                                        Reschedule
                                        <ArrowRight class="ml-1 h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <Separator />

                        <!-- Suggested Resolution -->
                        <div>
                            <h4 class="mb-1 text-sm font-medium text-muted-foreground">Suggested Resolution</h4>
                            <p class="text-sm">{{ conflict.suggested_resolution }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
