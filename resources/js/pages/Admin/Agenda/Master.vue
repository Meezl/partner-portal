<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CalendarDays, Clock, MapPin, Users, Printer, Download } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AdminLayout from '@/layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

interface AgendaSession {
    session: {
        id: number;
        title: string;
        format: string;
        partner_name: string;
    };
    room: {
        id: number;
        name: string;
    };
    resources: {
        id: number;
        name: string | null;
        resource_type: string;
    }[];
}

interface AgendaSlot {
    time_slot: {
        id: number;
        start_time: string;
        end_time: string;
        label: string | null;
        slot_type: string;
    };
    sessions: AgendaSession[];
}

interface AgendaDay {
    date: string;
    slots: AgendaSlot[];
}

const props = defineProps<{
    agenda: AgendaDay[];
}>();

const selectedDayIndex = ref(0);

const selectedDay = computed(() => props.agenda[selectedDayIndex.value]);

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatShortDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    });
}

function formatTime(time: string): string {
    return time.substring(0, 5);
}

function handlePrint() {
    window.print();
}
</script>

<template>
    <Head title="Master Agenda" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="font-heading text-2xl font-bold">Master Agenda</h1>
            <div class="flex items-center gap-2 print:hidden">
                <Button variant="outline" @click="handlePrint">
                    <Printer class="mr-2 h-4 w-4" />
                    Print
                </Button>
                <Button variant="outline" disabled>
                    <Download class="mr-2 h-4 w-4" />
                    Export
                </Button>
            </div>
        </div>

        <!-- Day Tabs -->
        <div v-if="agenda.length > 0" class="flex gap-2 overflow-x-auto print:hidden">
            <Button
                v-for="(day, index) in agenda"
                :key="day.date"
                :variant="selectedDayIndex === index ? 'default' : 'outline'"
                size="sm"
                @click="selectedDayIndex = index"
            >
                <CalendarDays class="mr-1 h-4 w-4" />
                {{ formatShortDate(day.date) }}
            </Button>
        </div>

        <div v-if="!selectedDay" class="rounded-lg border bg-card p-12 text-center text-muted-foreground">
            No agenda data available.
        </div>

        <div v-else class="space-y-4">
            <h2 class="font-heading text-lg font-semibold">{{ formatDate(selectedDay.date) }}</h2>

            <div v-for="slot in selectedDay.slots" :key="slot.time_slot.id" class="space-y-2">
                <!-- Time Slot Header -->
                <div class="flex items-center gap-2">
                    <div class="flex items-center gap-1 rounded-md bg-muted px-3 py-1.5 text-sm font-medium">
                        <Clock class="h-4 w-4 text-muted-foreground" />
                        {{ formatTime(slot.time_slot.start_time) }} - {{ formatTime(slot.time_slot.end_time) }}
                    </div>
                    <Badge v-if="slot.time_slot.label" variant="outline">
                        {{ slot.time_slot.label }}
                    </Badge>
                    <Badge v-if="slot.time_slot.slot_type !== 'session'" variant="secondary" class="capitalize">
                        {{ slot.time_slot.slot_type }}
                    </Badge>
                </div>

                <!-- Sessions in this slot -->
                <div v-if="slot.sessions.length > 0" class="ml-4 grid gap-2 lg:grid-cols-2">
                    <Card v-for="entry in slot.sessions" :key="entry.session.id" class="border-l-4 border-l-primary">
                        <CardContent class="p-4">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h4 class="font-medium">{{ entry.session.title }}</h4>
                                    <p class="mt-0.5 text-sm text-muted-foreground">
                                        {{ entry.session.partner_name }}
                                    </p>
                                </div>
                                <Badge variant="outline" class="capitalize text-xs">
                                    {{ entry.session.format }}
                                </Badge>
                            </div>

                            <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                                <span class="flex items-center gap-1">
                                    <MapPin class="h-3 w-3" />
                                    {{ entry.room.name }}
                                </span>
                                <template v-if="entry.resources.length > 0">
                                    <span
                                        v-for="resource in entry.resources"
                                        :key="resource.id"
                                        class="flex items-center gap-1"
                                    >
                                        <Users class="h-3 w-3" />
                                        {{ resource.name || 'TBD' }}
                                        <Badge variant="secondary" class="text-[10px] capitalize">
                                            {{ resource.resource_type }}
                                        </Badge>
                                    </span>
                                </template>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div v-else class="ml-4 rounded-lg border border-dashed p-3 text-center text-sm text-muted-foreground">
                    No sessions scheduled for this time slot.
                </div>

                <Separator class="mt-2" />
            </div>
        </div>
    </div>
</template>
