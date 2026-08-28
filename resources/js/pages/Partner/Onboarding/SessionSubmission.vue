<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Plus, Pencil, Trash2, CalendarDays, CalendarClock, Clock, MapPin } from 'lucide-vue-next';
import { ref } from 'vue';
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import PartnerLayout from '@/layouts/PartnerLayout.vue';
import { parseCalendarDate } from '@/lib/utils';
import type { Partner, ConferenceSession } from '@/types/partner';

defineOptions({ layout: PartnerLayout });

defineProps<{
    partner: Partner;
    sessions: ConferenceSession[];
}>();

const deletingSession = ref<ConferenceSession | null>(null);

function deleteSession(session: ConferenceSession) {
    deletingSession.value = null;
    router.delete(`/partner/sessions/${session.id}`);
}

function formatLabel(format: string) {
    return format.charAt(0).toUpperCase() + format.slice(1).replace(/_/g, ' ');
}

type TimeState = {
    /** 'pending' | 'approved' | 'booked' — drives the badge. */
    kind: 'pending' | 'approved' | 'booked';
    name: string;
    when: string;
    room: string | null;
};

function shortDate(date: string): string {
    return parseCalendarDate(date).toLocaleDateString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    });
}

/**
 * When a session's time comes from, in order of precedence:
 *   1. a slot the partner has requested and is awaiting approval on,
 *   2. the slot approved for them,
 *   3. the room booking the programme team made on the scheduling board.
 *
 * The third case matters because an admin can place a session in a room and
 * time the slot matrix does not describe, which releases the slot — without
 * this fallback the session would look unscheduled even though it is booked.
 */
function timeState(session: ConferenceSession): TimeState | null {
    const slot = session.requested_session_slot ?? session.session_slot ?? null;

    if (slot) {
        return {
            kind: session.requested_session_slot_id ? 'pending' : 'approved',
            name: slot.slot_code,
            when: slot.date ? `${shortDate(slot.date)} · ${slot.time_label}` : slot.time_label,
            room: slot.default_room?.name ?? null,
        };
    }

    const booking = session.schedule;

    if (booking?.time_slot) {
        return {
            kind: 'booked',
            name: booking.time_slot.label || 'Scheduled',
            when: `${shortDate(booking.time_slot.date)} · ${booking.time_slot.start_time.slice(0, 5)}–${booking.time_slot.end_time.slice(0, 5)}`,
            room: booking.room?.name ?? null,
        };
    }

    return null;
}
</script>

<template>
    <div class="space-y-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="font-heading text-3xl font-bold tracking-tight">Session Submissions</h1>
                <p class="text-muted-foreground mt-1">Manage your conference sessions.</p>
            </div>
            <Link href="/partner/sessions/create">
                <Button>
                    <Plus class="mr-2 h-4 w-4" />
                    Add Session
                </Button>
            </Link>
        </div>

        <div v-if="sessions.length === 0">
            <Card>
                <CardContent class="flex flex-col items-center gap-4 py-12">
                    <CalendarDays class="text-muted-foreground h-12 w-12" />
                    <div class="text-center">
                        <p class="font-medium">No sessions yet</p>
                        <p class="text-muted-foreground mt-1 text-sm">Create your first conference session to get started.</p>
                    </div>
                    <Link href="/partner/sessions/create">
                        <Button>
                            <Plus class="mr-2 h-4 w-4" />
                            Create Session
                        </Button>
                    </Link>
                </CardContent>
            </Card>
        </div>

        <div v-else class="space-y-4">
            <Card v-for="session in sessions" :key="session.id">
                <CardHeader>
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-1">
                            <CardTitle class="text-base">{{ session.title }}</CardTitle>
                            <CardDescription>
                                <Badge variant="outline" class="mr-2">{{ formatLabel(session.format) }}</Badge>
                                <StatusBadge :status="session.status" />
                            </CardDescription>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link :href="`/partner/sessions/${session.id}/edit`">
                                <Button variant="outline" size="sm">
                                    <Pencil class="mr-1 h-4 w-4" />
                                    Edit
                                </Button>
                            </Link>
                            <Button variant="outline" size="sm" class="text-red-600 hover:text-red-700" @click="deletingSession = session">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                        <template v-if="timeState(session)">
                            <span class="text-muted-foreground flex items-center gap-1.5">
                                <CalendarClock class="h-4 w-4" />
                                {{ timeState(session)!.name }}
                            </span>
                            <span class="text-muted-foreground flex items-center gap-1.5">
                                <Clock class="h-4 w-4" />
                                {{ timeState(session)!.when }}
                            </span>
                            <span
                                v-if="timeState(session)!.room"
                                class="text-muted-foreground flex items-center gap-1.5"
                            >
                                <MapPin class="h-4 w-4" />
                                {{ timeState(session)!.room }}
                            </span>
                            <Badge
                                v-if="timeState(session)!.kind === 'pending'"
                                variant="outline"
                                class="border-amber-400 text-amber-700 dark:text-amber-400"
                            >
                                Time pending approval
                            </Badge>
                            <Badge
                                v-else-if="timeState(session)!.kind === 'booked'"
                                variant="outline"
                                class="border-green-500 text-green-700 dark:text-green-400"
                            >
                                Scheduled by the programme team
                            </Badge>
                            <Badge v-else variant="outline" class="border-green-500 text-green-700 dark:text-green-400">
                                Time approved
                            </Badge>
                        </template>
                        <span v-else class="text-muted-foreground flex items-center gap-1.5">
                            <CalendarClock class="h-4 w-4" />
                            No date and time requested yet
                        </span>
                    </div>

                    <p v-if="session.description" class="text-muted-foreground text-sm line-clamp-2">
                        {{ session.description }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <ConfirmDialog
            :open="deletingSession !== null"
            title="Delete Session"
            description="Are you sure you want to delete this session? This action cannot be undone."
            confirm-label="Delete Session"
            variant="destructive"
            @confirm="deletingSession && deleteSession(deletingSession)"
            @cancel="deletingSession = null"
        />
    </div>
</template>
