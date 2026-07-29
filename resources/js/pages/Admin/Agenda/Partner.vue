<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, Clock, MapPin, Users, Building2 } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { Partner } from '@/types/partner';

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
        date: string;
        start_time: string;
        end_time: string;
        label: string | null;
    };
    sessions: AgendaSession[];
}

interface AgendaDay {
    date: string;
    slots: AgendaSlot[];
}

const props = defineProps<{
    partner: Partner;
    agenda: AgendaDay[];
}>();

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatTime(time: string): string {
    return time.substring(0, 5);
}
</script>

<template>
    <Head :title="`Agenda: ${partner.organization_name}`" />

    <div class="space-y-6">
        <div>
            <Link href="/admin/agenda/master" class="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                <ArrowLeft class="h-4 w-4" />
                Back to Master Agenda
            </Link>
            <div class="flex items-center gap-3">
                <Building2 class="h-6 w-6 text-primary" />
                <div>
                    <h1 class="font-heading text-2xl font-bold">{{ partner.organization_name }}</h1>
                    <p class="text-sm text-muted-foreground">Partner Agenda</p>
                </div>
            </div>
        </div>

        <div v-if="agenda.length === 0" class="rounded-lg border bg-card p-12 text-center text-muted-foreground">
            No scheduled sessions for this partner.
        </div>

        <div v-else class="space-y-8">
            <div v-for="day in agenda" :key="day.date">
                <h2 class="mb-4 flex items-center gap-2 font-heading text-lg font-semibold">
                    <CalendarDays class="h-5 w-5 text-primary" />
                    {{ formatDate(day.date) }}
                </h2>

                <div class="space-y-3">
                    <Card v-for="slot in day.slots" :key="slot.time_slot.id">
                        <CardContent class="p-4">
                            <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                <Clock class="h-4 w-4" />
                                {{ formatTime(slot.time_slot.start_time) }} - {{ formatTime(slot.time_slot.end_time) }}
                                <Badge v-if="slot.time_slot.label" variant="outline" class="text-xs">
                                    {{ slot.time_slot.label }}
                                </Badge>
                            </div>

                            <div class="mt-3 space-y-3">
                                <div
                                    v-for="entry in slot.sessions"
                                    :key="entry.session.id"
                                    class="rounded-lg border bg-muted/30 p-3"
                                >
                                    <div class="flex items-start justify-between">
                                        <h4 class="font-medium">{{ entry.session.title }}</h4>
                                        <Badge variant="outline" class="capitalize text-xs">
                                            {{ entry.session.format }}
                                        </Badge>
                                    </div>

                                    <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                                        <span class="flex items-center gap-1">
                                            <MapPin class="h-3 w-3" />
                                            {{ entry.room.name }}
                                        </span>
                                        <template v-if="entry.resources.length > 0">
                                            <Separator orientation="vertical" class="h-3" />
                                            <span
                                                v-for="resource in entry.resources"
                                                :key="resource.id"
                                                class="flex items-center gap-1"
                                            >
                                                <Users class="h-3 w-3" />
                                                {{ resource.name || 'TBD' }}
                                                ({{ resource.resource_type }})
                                            </span>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </div>
</template>
