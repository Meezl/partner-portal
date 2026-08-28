<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CalendarDays,
    Plus,
    Clock,
    MapPin,
    AlertTriangle,
    LayoutGrid,
    Pencil,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectTrigger,
    SelectValue,
    SelectContent,
    SelectItem,
} from '@/components/ui/select';
import AdminLayout from '@/layouts/AdminLayout.vue';
import {
    buildRoomFitWarnings,
    summarizeAllocationDays,
} from '@/lib/room-allocation.js';
import {
    buildScheduleAssignmentPath,
    buildScheduleDeletePath,
    buildScheduleUpdatePath,
    getUnscheduledSessions,
} from '@/lib/scheduling-workflow.js';
import { formatCalendarDate } from '@/lib/utils';
import type {
    ConferenceSession,
    Room,
    TimeSlot,
    SessionSchedule,
} from '@/types/partner';

defineOptions({ layout: AdminLayout });

type AllocationCell = {
    room_id: number;
    room_name: string;
    room_capacity: number;
    schedule:
        | (SessionSchedule & {
              session?: Pick<
                  ConferenceSession,
                  | 'id'
                  | 'partner_id'
                  | 'title'
                  | 'format'
                  | 'expected_participants'
              >;
              fit_warnings?: string[];
          })
        | null;
};

type AllocationSlot = {
    id: number;
    date: string;
    start_time: string;
    end_time: string;
    label: string | null;
    slot_type: string;
    scheduled_count: number;
    available_rooms: number;
    cells: AllocationCell[];
};

type AllocationDay = {
    date: string;
    label: string;
    slot_count: number;
    scheduled_sessions: number;
    occupancy_rate: number;
    slots: AllocationSlot[];
};

type RoomStat = {
    room_id: number;
    name: string;
    capacity: number;
    assignment_count: number;
    utilization_rate: number;
    format_suitability: string[];
    is_active: boolean;
};

const props = defineProps<{
    sessions: ConferenceSession[];
    rooms: Room[];
    timeSlots: TimeSlot[];
    schedules: (SessionSchedule & {
        session?: ConferenceSession;
        room?: Room;
        time_slot?: TimeSlot;
    })[];
    allocationSummary: {
        active_rooms: number;
        time_slots: number;
        scheduled_sessions: number;
        unscheduled_sessions: number;
        occupancy_rate: number;
    };
    allocationDays: AllocationDay[];
    roomStats: RoomStat[];
}>();

const showAssignDialog = ref(false);
const assignForm = useForm({
    conference_session_id: '' as string,
    room_id: '' as string,
    time_slot_id: '' as string,
});

const selectedDate = ref(props.allocationDays[0]?.date || '');

const selectedDay = computed(
    () =>
        props.allocationDays.find((day) => day.date === selectedDate.value) ??
        props.allocationDays[0] ??
        null,
);

const activeRooms = computed(() =>
    props.rooms.filter((room) => room.is_active),
);
const unscheduledSessions = computed(() =>
    getUnscheduledSessions(props.sessions, props.schedules),
);
const roomStatsById = computed(() =>
    Object.fromEntries(props.roomStats.map((room) => [room.room_id, room])),
);
const matrixTotals = computed(() =>
    summarizeAllocationDays(props.allocationDays),
);

function openAssignDialog() {
    assignForm.reset();
    assignForm.clearErrors();
    showAssignDialog.value = true;
}

function submitAssignment() {
    assignForm.post(
        buildScheduleAssignmentPath(assignForm.conference_session_id),
        {
            onSuccess: () => {
                showAssignDialog.value = false;
            },
        },
    );
}

/**
 * Problems with the pending assignment, evaluated live so the admin sees them
 * before submitting rather than getting a rejection afterwards. Mirrors the
 * server-side guards in SchedulingController::assignmentError().
 */
function conflictsFor(sessionId: string, roomId: string, timeSlotId: string, ignoreScheduleId?: number): string[] {
    const session = props.sessions.find((s) => String(s.id) === sessionId);
    const room = props.rooms.find((r) => String(r.id) === roomId);
    const problems: string[] = [];

    if (!session || !room) {
        return problems;
    }

    problems.push(...buildRoomFitWarnings(session, room));

    if (timeSlotId) {
        const inSlot = props.schedules.filter(
            (sch) =>
                String(sch.time_slot_id) === timeSlotId &&
                sch.id !== ignoreScheduleId,
        );

        if (inSlot.some((sch) => String(sch.room_id) === roomId)) {
            problems.push(`${room.name} is already booked for that time slot.`);
        }

        const partnerClash = inSlot.some(
            (sch) => sch.session?.partner_id === session.partner_id,
        );

        if (partnerClash) {
            problems.push('This partner already has a session in that time slot.');
        }
    }

    return problems;
}

const assignConflicts = computed(() =>
    conflictsFor(
        assignForm.conference_session_id,
        assignForm.room_id,
        assignForm.time_slot_id,
    ),
);

/* ---------- Editing and removing an existing assignment ---------- */

type ScheduledCell = AllocationCell & { schedule: NonNullable<AllocationCell['schedule']> };

const editingCell = ref<ScheduledCell | null>(null);
const removingCell = ref<ScheduledCell | null>(null);

const editForm = useForm({
    room_id: '' as string,
    time_slot_id: '' as string,
});

function openEditDialog(cell: ScheduledCell) {
    editingCell.value = cell;
    editForm.clearErrors();
    editForm.room_id = String(cell.schedule.room_id ?? cell.room_id);
    editForm.time_slot_id = String(cell.schedule.time_slot_id ?? '');
}

const editConflicts = computed(() =>
    editingCell.value
        ? conflictsFor(
              String(editingCell.value.schedule.conference_session_id),
              editForm.room_id,
              editForm.time_slot_id,
              editingCell.value.schedule.id,
          )
        : [],
);

function submitEdit() {
    const sessionId = editingCell.value?.schedule.conference_session_id;

    if (!sessionId) {
        return;
    }

    editForm.put(buildScheduleUpdatePath(sessionId), {
        preserveScroll: true,
        onSuccess: () => {
            editingCell.value = null;
        },
    });
}

function confirmRemove() {
    const sessionId = removingCell.value?.schedule.conference_session_id;

    if (!sessionId) {
        return;
    }

    router.delete(buildScheduleDeletePath(sessionId), {
        preserveScroll: true,
        onFinish: () => {
            removingCell.value = null;
        },
    });
}

function formatDate(dateStr: string): string {
    return formatCalendarDate(dateStr, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    });
}

function formatTime(time: string): string {
    return String(time).substring(0, 5);
}

function formatSessionFormat(format: string | undefined): string {
    if (!format) {
        return 'Session';
    }

    return format
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function getCellWarnings(cell: AllocationCell): string[] {
    if (cell.schedule?.fit_warnings?.length) {
        return cell.schedule.fit_warnings;
    }

    return buildRoomFitWarnings(
        cell.schedule?.session,
        props.rooms.find((room) => room.id === cell.room_id),
    );
}

const partnerColors: Record<number, string> = {};
const colorPalette = [
    'bg-blue-50 border-blue-200 text-blue-900',
    'bg-green-50 border-green-200 text-green-900',
    'bg-orange-50 border-orange-200 text-orange-900',
    'bg-teal-50 border-teal-200 text-teal-900',
    'bg-amber-50 border-amber-200 text-amber-900',
    'bg-rose-50 border-rose-200 text-rose-900',
    'bg-indigo-50 border-indigo-200 text-indigo-900',
    'bg-cyan-50 border-cyan-200 text-cyan-900',
];
let colorIndex = 0;

function getPartnerColor(partnerId: number): string {
    if (!partnerColors[partnerId]) {
        partnerColors[partnerId] =
            colorPalette[colorIndex % colorPalette.length];
        colorIndex++;
    }

    return partnerColors[partnerId];
}
</script>

<template>
    <Head title="Scheduling Board" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
        >
            <div>
                <h1 class="font-heading text-2xl font-bold">
                    Room Allocation Board
                </h1>
                <p class="text-sm text-muted-foreground">
                    Schedule sessions against room capacity, suitability, and
                    daily slot load.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <Badge
                    v-if="allocationSummary.unscheduled_sessions > 0"
                    variant="outline"
                    class="border-warning bg-warning/10 text-warning"
                >
                    {{ allocationSummary.unscheduled_sessions }} unscheduled
                </Badge>
                <Button @click="openAssignDialog">
                    <Plus class="mr-2 h-4 w-4" />
                    Assign Session
                </Button>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <Card>
                <CardHeader class="pb-3">
                    <CardDescription>Active rooms</CardDescription>
                    <CardTitle class="text-3xl">{{
                        allocationSummary.active_rooms
                    }}</CardTitle>
                </CardHeader>
                <CardContent class="text-sm text-muted-foreground">
                    Rooms available for phase 6 scheduling.
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-3">
                    <CardDescription>Schedulable slots</CardDescription>
                    <CardTitle class="text-3xl">{{
                        allocationSummary.time_slots
                    }}</CardTitle>
                </CardHeader>
                <CardContent class="text-sm text-muted-foreground">
                    {{ matrixTotals.slotCount }} slot rows across all conference
                    days.
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-3">
                    <CardDescription>Assigned sessions</CardDescription>
                    <CardTitle class="text-3xl">{{
                        allocationSummary.scheduled_sessions
                    }}</CardTitle>
                </CardHeader>
                <CardContent class="text-sm text-muted-foreground">
                    {{ matrixTotals.scheduledSessions }} scheduled sessions
                    currently on the board.
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-3">
                    <CardDescription>Occupancy</CardDescription>
                    <CardTitle class="text-3xl"
                        >{{ allocationSummary.occupancy_rate }}%</CardTitle
                    >
                </CardHeader>
                <CardContent class="text-sm text-muted-foreground">
                    Share of room x slot capacity already allocated.
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
            <Card>
                <CardHeader class="pb-4">
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <LayoutGrid class="h-4 w-4" />
                        Daily Allocation Matrix
                    </CardTitle>
                    <CardDescription>
                        Use the workbook-style day tabs below to review slot
                        load and room coverage.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="flex gap-2 overflow-x-auto pb-1">
                        <Button
                            v-for="day in allocationDays"
                            :key="day.date"
                            :variant="
                                selectedDate === day.date
                                    ? 'default'
                                    : 'outline'
                            "
                            size="sm"
                            class="min-w-fit"
                            @click="selectedDate = day.date"
                        >
                            <CalendarDays class="mr-1 h-4 w-4" />
                            {{ day.label }}
                            <span class="ml-2 text-xs opacity-80"
                                >{{ day.scheduled_sessions }}/{{
                                    day.slot_count *
                                    Math.max(activeRooms.length, 1)
                                }}</span
                            >
                        </Button>
                    </div>

                    <div
                        v-if="selectedDay"
                        class="rounded-xl border border-border/70 bg-muted/20 p-3"
                    >
                        <div
                            class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between"
                        >
                            <div>
                                <div class="font-medium">
                                    {{ selectedDay.label }}
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    {{ selectedDay.slot_count }} slots,
                                    {{
                                        selectedDay.scheduled_sessions
                                    }}
                                    scheduled sessions
                                </div>
                            </div>
                            <Badge variant="outline" class="w-fit">
                                Occupancy {{ selectedDay.occupancy_rate }}%
                            </Badge>
                        </div>
                    </div>

                    <div v-if="selectedDay" class="overflow-x-auto">
                        <div class="min-w-[980px]">
                            <div
                                class="grid gap-1"
                                :style="{
                                    gridTemplateColumns: `180px repeat(${activeRooms.length}, minmax(180px, 1fr))`,
                                }"
                            >
                                <div
                                    class="rounded-lg bg-muted p-3 text-left text-xs font-medium text-muted-foreground"
                                >
                                    <Clock class="mb-1 h-4 w-4" />
                                    Time slot
                                </div>
                                <div
                                    v-for="room in activeRooms"
                                    :key="room.id"
                                    class="rounded-lg bg-muted p-3 text-left text-xs"
                                >
                                    <div
                                        class="flex items-center gap-2 font-medium"
                                    >
                                        <MapPin
                                            class="h-4 w-4 text-muted-foreground"
                                        />
                                        {{ room.name }}
                                    </div>
                                    <div class="mt-2 text-muted-foreground">
                                        Capacity {{ room.capacity }}
                                    </div>
                                    <div class="mt-1 text-muted-foreground">
                                        {{
                                            roomStatsById[room.id]
                                                ?.assignment_count ?? 0
                                        }}
                                        assigned
                                        <span class="mx-1">•</span>
                                        {{
                                            roomStatsById[room.id]
                                                ?.utilization_rate ?? 0
                                        }}% utilized
                                    </div>
                                </div>
                            </div>

                            <div
                                v-for="slot in selectedDay.slots"
                                :key="slot.id"
                                class="mt-1 grid gap-1"
                                :style="{
                                    gridTemplateColumns: `180px repeat(${activeRooms.length}, minmax(180px, 1fr))`,
                                }"
                            >
                                <div
                                    class="rounded-lg border bg-card p-3 text-xs"
                                >
                                    <div class="font-medium">
                                        {{ formatTime(slot.start_time) }} -
                                        {{ formatTime(slot.end_time) }}
                                    </div>
                                    <div
                                        v-if="slot.label"
                                        class="mt-1 text-muted-foreground"
                                    >
                                        {{ slot.label }}
                                    </div>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <Badge variant="outline"
                                            >{{
                                                slot.scheduled_count
                                            }}
                                            scheduled</Badge
                                        >
                                        <Badge variant="outline"
                                            >{{
                                                slot.available_rooms
                                            }}
                                            available</Badge
                                        >
                                    </div>
                                </div>

                                <div
                                    v-for="cell in slot.cells"
                                    :key="`${slot.id}-${cell.room_id}`"
                                    class="min-h-[124px] rounded-lg border p-2"
                                    :class="
                                        cell.schedule
                                            ? 'bg-card'
                                            : 'bg-muted/30'
                                    "
                                >
                                    <template
                                        v-if="
                                            cell.schedule &&
                                            cell.schedule.session
                                        "
                                    >
                                        <div
                                            class="rounded-lg border p-3 text-xs"
                                            :class="[
                                                getPartnerColor(
                                                    cell.schedule.session
                                                        .partner_id,
                                                ),
                                                getCellWarnings(cell).length > 0
                                                    ? 'border-amber-400 ring-1 ring-amber-200'
                                                    : '',
                                            ]"
                                        >
                                            <div
                                                class="leading-tight font-medium"
                                            >
                                                {{
                                                    cell.schedule.session.title
                                                }}
                                            </div>
                                            <div
                                                class="mt-2 flex flex-wrap gap-2 text-[10px] opacity-80"
                                            >
                                                <span>{{
                                                    formatSessionFormat(
                                                        cell.schedule.session
                                                            .format,
                                                    )
                                                }}</span>
                                                <span
                                                    v-if="
                                                        cell.schedule.session
                                                            .expected_participants
                                                    "
                                                >
                                                    {{
                                                        cell.schedule.session
                                                            .expected_participants
                                                    }}
                                                    pax
                                                </span>
                                            </div>
                                            <div
                                                v-if="
                                                    getCellWarnings(cell)
                                                        .length > 0
                                                "
                                                class="mt-3 space-y-1 rounded-md bg-amber-100/70 p-2 text-[10px] text-amber-950"
                                            >
                                                <div
                                                    v-for="warning in getCellWarnings(
                                                        cell,
                                                    )"
                                                    :key="warning"
                                                    class="flex items-start gap-1"
                                                >
                                                    <AlertTriangle
                                                        class="mt-0.5 h-3 w-3 shrink-0"
                                                    />
                                                    <span>{{ warning }}</span>
                                                </div>
                                            </div>

                                            <div
                                                class="mt-3 flex items-center gap-1 border-t border-current/15 pt-2"
                                            >
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    class="h-7 px-2 text-[11px]"
                                                    @click="openEditDialog(cell as ScheduledCell)"
                                                >
                                                    <Pencil class="mr-1 h-3 w-3" />
                                                    Edit
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    class="h-7 px-2 text-[11px] text-destructive hover:text-destructive"
                                                    @click="removingCell = cell as ScheduledCell"
                                                >
                                                    <Trash2 class="mr-1 h-3 w-3" />
                                                    Remove
                                                </Button>
                                            </div>
                                        </div>
                                    </template>
                                    <div
                                        v-else
                                        class="flex h-full min-h-[100px] items-center justify-center rounded-lg border border-dashed border-border/70 text-xs text-muted-foreground"
                                    >
                                        Available
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
                    >
                        No time slots have been configured for the active
                        conference yet.
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-lg">Room Readiness</CardTitle>
                    <CardDescription>
                        Mirrors the workbook’s room details table for quick fit
                        checks.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div
                        v-for="room in roomStats"
                        :key="room.room_id"
                        class="rounded-xl border border-border/70 p-3"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div class="font-medium">{{ room.name }}</div>
                            <Badge variant="outline"
                                >{{ room.utilization_rate }}%</Badge
                            >
                        </div>
                        <div class="mt-1 text-sm text-muted-foreground">
                            Capacity {{ room.capacity }} •
                            {{ room.assignment_count }} assignments
                        </div>
                        <div class="mt-3 flex flex-wrap gap-1">
                            <Badge
                                v-for="format in room.format_suitability"
                                :key="format"
                                variant="secondary"
                                class="capitalize"
                            >
                                {{ formatSessionFormat(format) }}
                            </Badge>
                            <span
                                v-if="room.format_suitability.length === 0"
                                class="text-xs text-muted-foreground"
                            >
                                No suitability limits configured
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Dialog
            :open="showAssignDialog"
            @update:open="showAssignDialog = $event"
        >
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Assign Session</DialogTitle>
                    <DialogDescription>
                        Select a session, room, and time slot to create a room
                        allocation.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label>Session</Label>
                        <Select v-model="assignForm.conference_session_id">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select a session" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="session in unscheduledSessions"
                                    :key="session.id"
                                    :value="String(session.id)"
                                >
                                    {{ session.title }} ({{
                                        formatSessionFormat(session.format)
                                    }})
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label>Room</Label>
                        <Select v-model="assignForm.room_id">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select a room" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="room in activeRooms"
                                    :key="room.id"
                                    :value="String(room.id)"
                                >
                                    {{ room.name }} ({{ room.capacity }} cap.)
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label>Time Slot</Label>
                        <Select v-model="assignForm.time_slot_id">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select a time slot" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="slot in timeSlots"
                                    :key="slot.id"
                                    :value="String(slot.id)"
                                >
                                    {{ formatDate(slot.date) }}
                                    {{ formatTime(slot.start_time) }}-{{
                                        formatTime(slot.end_time)
                                    }}
                                    <span v-if="slot.label">
                                        ({{ slot.label }})</span
                                    >
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div
                        v-if="assignConflicts.length"
                        class="space-y-1 rounded-md border border-amber-400 bg-amber-50 p-3 text-xs text-amber-900"
                    >
                        <div class="flex items-center gap-1.5 font-medium">
                            <AlertTriangle class="h-3.5 w-3.5" />
                            This assignment will be rejected
                        </div>
                        <div v-for="problem in assignConflicts" :key="problem" class="pl-5">
                            {{ problem }}
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showAssignDialog = false"
                        >Cancel</Button
                    >
                    <Button
                        :disabled="
                            !assignForm.conference_session_id ||
                            !assignForm.room_id ||
                            !assignForm.time_slot_id ||
                            assignConflicts.length > 0 ||
                            assignForm.processing
                        "
                        @click="submitAssignment"
                    >
                        Assign
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Edit an existing assignment -->
        <Dialog
            :open="editingCell !== null"
            @update:open="(val: boolean) => { if (!val) editingCell = null }"
        >
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Move session</DialogTitle>
                    <DialogDescription>
                        {{ editingCell?.schedule.session?.title }} — currently in
                        {{ editingCell?.room_name }}. The partner is emailed when
                        the assignment changes.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-2">
                    <div class="space-y-2">
                        <Label>Room</Label>
                        <Select v-model="editForm.room_id">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select room" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="room in activeRooms"
                                    :key="room.id"
                                    :value="String(room.id)"
                                >
                                    {{ room.name }} — capacity {{ room.capacity }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label>Time slot</Label>
                        <Select v-model="editForm.time_slot_id">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select time slot" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="slot in timeSlots"
                                    :key="slot.id"
                                    :value="String(slot.id)"
                                >
                                    {{ formatDate(slot.date) }} ·
                                    {{ formatTime(slot.start_time) }}–{{ formatTime(slot.end_time) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div
                        v-if="editConflicts.length"
                        class="space-y-1 rounded-md border border-amber-400 bg-amber-50 p-3 text-xs text-amber-900"
                    >
                        <div class="flex items-center gap-1.5 font-medium">
                            <AlertTriangle class="h-3.5 w-3.5" />
                            This move will be rejected
                        </div>
                        <div v-for="problem in editConflicts" :key="problem" class="pl-5">
                            {{ problem }}
                        </div>
                    </div>

                    <p v-else class="text-muted-foreground text-xs">
                        Room clashes, partner double-bookings, capacity and format
                        fit are all re-checked on save.
                    </p>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="editingCell = null">Cancel</Button>
                    <Button
                        :disabled="
                            !editForm.room_id ||
                            !editForm.time_slot_id ||
                            editConflicts.length > 0 ||
                            editForm.processing
                        "
                        @click="submitEdit"
                    >
                        Save changes
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Remove an assignment -->
        <ConfirmDialog
            :open="removingCell !== null"
            title="Remove assignment"
            :description="`Unassign ${removingCell?.schedule.session?.title ?? 'this session'} from ${removingCell?.room_name ?? 'its room'}? It returns to the unscheduled list and any resources assigned to this slot are dropped. The partner is not notified.`"
            confirm-label="Remove assignment"
            variant="destructive"
            @confirm="confirmRemove"
            @cancel="removingCell = null"
        />
    </div>
</template>
