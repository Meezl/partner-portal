<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CalendarClock,
    CheckCircle,
    ClipboardCheck,
    Clock,
    LayoutGrid,
    MapPin,
    Pencil,
    Undo2,
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { formatCalendarDate } from '@/lib/utils';
import type { ConferenceSession, Partner, SessionSlot } from '@/types/partner';

defineOptions({ layout: AdminLayout });

type ReviewSession = ConferenceSession & {
    partner?: Partner;
    session_slot?: SessionSlot | null;
    requested_session_slot?: SessionSlot | null;
    schedule?: {
        room?: { id: number; name: string } | null;
        time_slot?: {
            id: number;
            date: string;
            start_time: string;
            end_time: string;
            label?: string | null;
        } | null;
    } | null;
};

const props = defineProps<{
    sessions: ReviewSession[];
    filters: { status: string };
    statuses: { value: string; label: string }[];
    availableSlots: SessionSlot[];
    counts: Record<string, number>;
}>();

const expanded = ref<number | null>(null);

function toggle(id: number) {
    expanded.value = expanded.value === id ? null : id;
}

function setStatus(status: string) {
    router.get('/admin/sessions', { status }, { preserveScroll: true, preserveState: true });
}

function slotLabel(slot?: SessionSlot | null): string | null {
    if (!slot) {
        return null;
    }

    const date = slot.date ? formatCalendarDate(slot.date, { weekday: 'short', month: 'short', day: 'numeric' }) : null;

    return `${slot.slot_code} · ${date ? `${date} · ` : ''}${slot.time_label}`;
}

/**
 * The room + time assigned on the scheduling board. This is a separate system
 * from the partner-facing slot above: the slot is what the partner asked for
 * and the programme team approved, the board is the operational room booking.
 */
function boardAssignment(session: ReviewSession): { room: string; time: string | null; label: string | null } | null {
    const schedule = session.schedule;

    if (!schedule?.room) {
        return null;
    }

    const slot = schedule.time_slot;

    return {
        room: schedule.room.name,
        time: slot
            ? `${formatCalendarDate(slot.date, { weekday: 'short', month: 'short', day: 'numeric' })} · ${slot.start_time.slice(0, 5)}–${slot.end_time.slice(0, 5)}`
            : null,
        label: slot?.label ?? null,
    };
}

/** Everything the partner filled in, laid out for review. */
function requirementRows(session: ReviewSession): { label: string; value: string }[] {
    const reqs = (session.special_requirements ?? {}) as Record<string, unknown>;

    return [
        { label: 'AV equipment', value: reqs.av_equipment ? 'Required' : 'Not required' },
        { label: 'Translation', value: reqs.translation ? 'Required' : 'Not required' },
        { label: 'Seating', value: String(reqs.seating_type ?? '—').replace(/_/g, ' ') },
        { label: 'Catering', value: reqs.catering ? 'Required' : 'Not required' },
    ];
}

/* ---------------- Approve ---------------- */

const approving = ref<ReviewSession | null>(null);
const approveForm = useForm({ notes: '' });

function submitApprove() {
    if (!approving.value) {
        return;
    }

    approveForm.post(`/admin/sessions/${approving.value.id}/approve`, {
        preserveScroll: true,
        onSuccess: () => {
            approving.value = null;
            approveForm.reset();
        },
    });
}

/* ---------------- Send back ---------------- */

const rejecting = ref<ReviewSession | null>(null);
const rejectForm = useForm({ notes: '' });

function submitReject() {
    if (!rejecting.value) {
        return;
    }

    rejectForm.post(`/admin/sessions/${rejecting.value.id}/reject`, {
        preserveScroll: true,
        onSuccess: () => {
            rejecting.value = null;
            rejectForm.reset();
        },
    });
}

/* ---------------- Edit (title + slot only) ---------------- */

const editing = ref<ReviewSession | null>(null);
const editForm = useForm({ title: '', session_slot_id: null as number | null });

function openEdit(session: ReviewSession) {
    editing.value = session;
    editForm.clearErrors();
    editForm.title = session.title;
    editForm.session_slot_id = session.session_slot_id ?? null;
}

function submitEdit() {
    if (!editing.value) {
        return;
    }

    editForm.put(`/admin/sessions/${editing.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
    });
}

/** Slots the edit dialog may offer: free ones plus the session's own. */
const slotOptions = computed(() => {
    const own = editing.value?.session_slot;
    const list = [...props.availableSlots];

    if (own && !list.some((s) => s.id === own.id)) {
        list.unshift(own);
    }

    return list;
});

const submittedCount = computed(() => props.counts.submitted ?? 0);
</script>

<template>
    <Head title="Session Review" />

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-heading text-2xl font-bold">Session Review</h1>
                <p class="text-muted-foreground text-sm">
                    Read each submitted session in full, then approve it into the
                    programme or send it back to the partner.
                </p>
            </div>
            <Badge variant="secondary">
                <ClipboardCheck class="mr-1 h-3 w-3" />
                {{ submittedCount }} awaiting review
            </Badge>
        </div>

        <div class="flex flex-wrap gap-2">
            <Button
                :variant="filters.status === 'all' ? 'default' : 'outline'"
                size="sm"
                @click="setStatus('all')"
            >
                All
            </Button>
            <Button
                v-for="s in statuses"
                :key="s.value"
                :variant="filters.status === s.value ? 'default' : 'outline'"
                size="sm"
                @click="setStatus(s.value)"
            >
                {{ s.label }}
                <span v-if="counts[s.value]" class="ml-1.5 opacity-70">{{ counts[s.value] }}</span>
            </Button>
        </div>

        <Card v-if="sessions.length === 0">
            <CardContent class="py-12 text-center">
                <ClipboardCheck class="text-muted-foreground mx-auto h-10 w-10" />
                <p class="mt-3 font-medium">Nothing here</p>
                <p class="text-muted-foreground mt-1 text-sm">
                    No sessions with this status.
                </p>
            </CardContent>
        </Card>

        <Card v-for="session in sessions" v-else :key="session.id">
            <CardHeader class="cursor-pointer" @click="toggle(session.id)">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 space-y-1">
                        <CardTitle class="text-base">{{ session.title }}</CardTitle>
                        <CardDescription class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ session.partner?.organization_name ?? '—' }}</span>
                            <Badge variant="outline" class="capitalize">
                                {{ String(session.format).replace(/_/g, ' ') }}
                            </Badge>
                            <StatusBadge :status="session.status" type="session" />
                            <Badge
                                v-if="session.requested_session_slot_id"
                                variant="outline"
                                class="border-amber-400 text-amber-700 dark:text-amber-400"
                            >
                                Time change pending
                            </Badge>
                        </CardDescription>
                    </div>

                    <div class="flex shrink-0 items-center gap-1" @click.stop>
                        <Button variant="outline" size="sm" @click="openEdit(session)">
                            <Pencil class="mr-1 h-4 w-4" />
                            Edit
                        </Button>
                        <Button
                            v-if="session.status !== 'confirmed'"
                            variant="outline"
                            size="sm"
                            class="text-green-700 hover:text-green-800"
                            @click="approving = session"
                        >
                            <CheckCircle class="mr-1 h-4 w-4" />
                            Approve
                        </Button>
                        <Button
                            v-if="session.status !== 'draft'"
                            variant="outline"
                            size="sm"
                            class="text-destructive hover:text-destructive"
                            @click="rejecting = session"
                        >
                            <Undo2 class="mr-1 h-4 w-4" />
                            Send back
                        </Button>
                    </div>
                </div>

                <div class="text-muted-foreground flex flex-wrap items-center gap-x-4 gap-y-1 pt-1 text-sm">
                    <span class="flex items-center gap-1.5">
                        <CalendarClock class="h-4 w-4" />
                        {{ slotLabel(session.requested_session_slot ?? session.session_slot) ?? 'No date/time yet' }}
                    </span>
                    <span v-if="boardAssignment(session)" class="flex items-center gap-1.5">
                        <MapPin class="h-4 w-4" />
                        {{ boardAssignment(session)!.room }}<template v-if="boardAssignment(session)!.time">
                            · {{ boardAssignment(session)!.time }}</template>
                    </span>
                    <span v-if="session.expected_participants" class="flex items-center gap-1.5">
                        <Users class="h-4 w-4" />
                        {{ session.expected_participants }} expected
                    </span>
                    <span class="flex items-center gap-1.5">
                        <Clock class="h-4 w-4" />
                        {{ session.submitted_at ? `Submitted ${formatCalendarDate(session.submitted_at, { month: 'short', day: 'numeric', year: 'numeric' })}` : 'Not submitted' }}
                    </span>
                </div>
            </CardHeader>

            <CardContent v-if="expanded === session.id" class="space-y-5 border-t pt-5 text-sm">
                <div v-if="session.description">
                    <h4 class="mb-1 font-medium">Description</h4>
                    <p class="text-muted-foreground whitespace-pre-line">{{ session.description }}</p>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <h4 class="mb-1 font-medium">Target audience</h4>
                        <p class="text-muted-foreground">{{ session.target_audience || '—' }}</p>
                    </div>
                    <div>
                        <h4 class="mb-1 font-medium">Open to all attendees</h4>
                        <p class="text-muted-foreground">{{ session.is_open ? 'Yes' : 'No — invite only' }}</p>
                    </div>
                    <div>
                        <h4 class="mb-1 font-medium">Organizers</h4>
                        <p class="text-muted-foreground">
                            {{ session.organizers?.length ? session.organizers.join(', ') : '—' }}
                        </p>
                    </div>
                    <div>
                        <h4 class="mb-1 font-medium">Co-hosts</h4>
                        <p class="text-muted-foreground">
                            {{ session.co_hosts?.length ? session.co_hosts.join(', ') : '—' }}
                        </p>
                    </div>
                </div>

                <div>
                    <h4 class="mb-2 font-medium">Special requirements</h4>
                    <div class="grid gap-2 sm:grid-cols-4">
                        <div
                            v-for="row in requirementRows(session)"
                            :key="row.label"
                            class="bg-muted/40 rounded-md border p-2"
                        >
                            <div class="text-muted-foreground text-xs">{{ row.label }}</div>
                            <div class="capitalize">{{ row.value }}</div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <h4 class="mb-1 font-medium">Partner contact</h4>
                        <p class="text-muted-foreground">
                            {{ session.partner?.contact_person || '—' }}
                            <template v-if="session.partner?.email">
                                · {{ session.partner.email }}
                            </template>
                        </p>
                    </div>
                    <div>
                        <h4 class="mb-1 font-medium">Approved slot</h4>
                        <p class="text-muted-foreground">
                            {{ slotLabel(session.session_slot) ?? 'Not yet assigned' }}
                        </p>
                        <p v-if="session.requested_session_slot" class="mt-1 text-amber-700 dark:text-amber-400">
                            Requested: {{ slotLabel(session.requested_session_slot) }}
                        </p>
                    </div>

                    <div>
                        <h4 class="mb-1 flex items-center gap-1.5 font-medium">
                            <LayoutGrid class="h-4 w-4" />
                            Scheduling board
                        </h4>
                        <template v-if="boardAssignment(session)">
                            <p class="text-muted-foreground">
                                {{ boardAssignment(session)!.room }}
                                <template v-if="boardAssignment(session)!.time">
                                    · {{ boardAssignment(session)!.time }}
                                </template>
                            </p>
                            <p v-if="boardAssignment(session)!.label" class="text-muted-foreground text-xs">
                                {{ boardAssignment(session)!.label }}
                            </p>
                        </template>
                        <p v-else class="text-muted-foreground">
                            No room booked yet —
                            <a href="/admin/scheduling" class="underline">assign on the board</a>
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Approve -->
        <Dialog :open="approving !== null" @update:open="(v: boolean) => { if (!v) approving = null }">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Approve session</DialogTitle>
                    <DialogDescription>
                        “{{ approving?.title }}” will be confirmed for the programme
                        and the partner will be emailed.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-2 py-2">
                    <Label for="approve-notes">Note for the partner (optional)</Label>
                    <textarea
                        id="approve-notes"
                        v-model="approveForm.notes"
                        rows="3"
                        class="border-input bg-background focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                        placeholder="Anything they should know…"
                    />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="approving = null">Cancel</Button>
                    <Button :disabled="approveForm.processing" @click="submitApprove">
                        Approve session
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Send back -->
        <Dialog :open="rejecting !== null" @update:open="(v: boolean) => { if (!v) rejecting = null }">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Send back to partner</DialogTitle>
                    <DialogDescription>
                        “{{ rejecting?.title }}” becomes a draft again so the partner
                        can revise and resubmit it. Any date/time it holds is released.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-2 py-2">
                    <Label for="reject-notes">What needs to change?</Label>
                    <textarea
                        id="reject-notes"
                        v-model="rejectForm.notes"
                        rows="4"
                        class="border-input bg-background focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                        placeholder="This is emailed to the partner, so be specific."
                    />
                    <p v-if="rejectForm.errors.notes" class="text-destructive text-sm">
                        {{ rejectForm.errors.notes }}
                    </p>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="rejecting = null">Cancel</Button>
                    <Button
                        variant="destructive"
                        :disabled="!rejectForm.notes.trim() || rejectForm.processing"
                        @click="submitReject"
                    >
                        Send back
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Edit: title + slot only -->
        <Dialog :open="editing !== null" @update:open="(v: boolean) => { if (!v) editing = null }">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Edit session</DialogTitle>
                    <DialogDescription>
                        The programme team owns the title and the date/time. Everything
                        else stays with the partner.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-2">
                    <div class="space-y-2">
                        <Label for="edit-title">Title</Label>
                        <Input id="edit-title" v-model="editForm.title" />
                        <p v-if="editForm.errors.title" class="text-destructive text-sm">
                            {{ editForm.errors.title }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label>Date &amp; time slot</Label>
                        <select
                            v-model="editForm.session_slot_id"
                            class="border-input bg-background focus-visible:ring-ring h-9 w-full rounded-md border px-3 text-sm focus-visible:ring-2 focus-visible:outline-none"
                        >
                            <option :value="null">No slot assigned</option>
                            <option v-for="slot in slotOptions" :key="slot.id" :value="slot.id">
                                {{ slotLabel(slot) }}<template v-if="slot.default_room"> · {{ slot.default_room.name }}</template>
                            </option>
                        </select>
                        <p v-if="editForm.errors.session_slot_id" class="text-destructive text-sm">
                            {{ editForm.errors.session_slot_id }}
                        </p>
                        <p class="text-muted-foreground text-xs">
                            Setting a time here applies immediately and supersedes any
                            pending request from the partner.
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="editing = null">Cancel</Button>
                    <Button :disabled="!editForm.title.trim() || editForm.processing" @click="submitEdit">
                        Save changes
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
