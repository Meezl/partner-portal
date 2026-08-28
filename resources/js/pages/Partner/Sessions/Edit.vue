<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Save, Plus, X, CalendarClock, MapPin } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import SessionSlotPicker from '@/components/shared/SessionSlotPicker.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select';
import PartnerLayout from '@/layouts/PartnerLayout.vue';
import { formatCalendarDate, parseCalendarDate } from '@/lib/utils';
import type { Conference, Partner, ConferenceSession, SessionFormat, SessionSlot } from '@/types/partner';

defineOptions({ layout: PartnerLayout });

const props = defineProps<{
    partner: Partner;
    session: ConferenceSession;
    conference?: Conference | null;
    availableSlots?: SessionSlot[];
}>();

const pendingSlotId = computed(() => props.session.requested_session_slot_id ?? null);
const approvedSlotId = computed(() => props.session.session_slot_id ?? null);
const timePending = computed(() => pendingSlotId.value !== null);

const currentSlot = computed(
    () => props.session.requested_session_slot ?? props.session.session_slot ?? null,
);

function slotSchedule(slot: SessionSlot | null): string {
    if (!slot) {
        return 'No date and time chosen yet';
    }

    const date = slot.date
        ? parseCalendarDate(slot.date).toLocaleDateString(undefined, {
              weekday: 'long',
              month: 'long',
              day: 'numeric',
              year: 'numeric',
          })
        : null;

    return date ? `${date} · ${slot.time_label}` : slot.time_label;
}

/**
 * The room + time the programme team has booked on the scheduling board.
 *
 * Normally this mirrors the approved slot. It is shown on its own when the
 * session has no slot — an admin can place a session in a room and time the
 * slot matrix does not describe, which releases the slot, and without this the
 * panel would claim the session is unscheduled while it is actually booked.
 */
const boardBooking = computed(() => {
    const schedule = props.session.schedule;

    if (!schedule?.time_slot) {
        return null;
    }

    const slot = schedule.time_slot;

    return {
        room: schedule.room?.name ?? null,
        when: `${parseCalendarDate(slot.date).toLocaleDateString(undefined, {
            weekday: 'long',
            month: 'long',
            day: 'numeric',
            year: 'numeric',
        })} · ${slot.start_time.slice(0, 5)}–${slot.end_time.slice(0, 5)}`,
        label: slot.label ?? null,
    };
});

/** True when the board booking is the only record of this session's time. */
const bookingOnly = computed(() => boardBooking.value !== null && currentSlot.value === null);

const specialReqs = (props.session.special_requirements ?? {}) as Record<string, unknown>;

const form = useForm({
    title: props.session.title,
    description: props.session.description ?? '',
    format: props.session.format as SessionFormat,
    organizers: (props.session.organizers ?? []) as string[],
    co_hosts: (props.session.co_hosts ?? []) as string[],
    target_audience: props.session.target_audience ?? '',
    expected_participants: props.session.expected_participants,
    is_open: props.session.is_open,
    session_slot_id: (props.session.requested_session_slot_id ?? props.session.session_slot_id ?? null) as number | null,
    slot_reason: '',
    special_requirements: {
        av_equipment: (specialReqs.av_equipment as boolean) ?? false,
        translation: (specialReqs.translation as boolean) ?? false,
        seating_type: (specialReqs.seating_type as string) ?? 'theater',
        catering: (specialReqs.catering as boolean) ?? false,
    },
});

const newOrganizer = ref('');
const newCoHost = ref('');

function addOrganizer() {
    const val = newOrganizer.value.trim();

    if (val && !form.organizers.includes(val)) {
        form.organizers.push(val);
        newOrganizer.value = '';
    }
}

function removeOrganizer(index: number) {
    form.organizers.splice(index, 1);
}

function addCoHost() {
    const val = newCoHost.value.trim();

    if (val && !form.co_hosts.includes(val)) {
        form.co_hosts.push(val);
        newCoHost.value = '';
    }
}

function removeCoHost(index: number) {
    form.co_hosts.splice(index, 1);
}

function submit() {
    form.put(`/partner/sessions/${props.session.id}`);
}

const sessionFormats: { value: SessionFormat; label: string }[] = [
    { value: 'panel', label: 'Panel Discussion' },
    { value: 'workshop', label: 'Workshop' },
    { value: 'plenary', label: 'Plenary' },
    { value: 'roundtable', label: 'Roundtable' },
    { value: 'exhibition', label: 'Exhibition' },
    { value: 'side_event', label: 'Side Event' },
];

const seatingTypes = [
    { value: 'theater', label: 'Theater' },
    { value: 'classroom', label: 'Classroom' },
    { value: 'boardroom', label: 'Boardroom' },
    { value: 'u_shape', label: 'U-Shape' },
    { value: 'round_tables', label: 'Round Tables' },
];
</script>

<template>
    <div class="space-y-8">
        <div>
            <h1 class="font-heading text-3xl font-bold tracking-tight">Edit Session</h1>
            <p class="text-muted-foreground mt-1">Update the details for "{{ session.title }}".</p>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Session Details</CardTitle>
                    <CardDescription>Basic information about the session.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <div class="space-y-2">
                        <Label for="title">Session Title</Label>
                        <Input id="title" v-model="form.title" placeholder="Enter session title" />
                        <InputError :message="form.errors.title" />
                    </div>

                    <div class="space-y-2">
                        <Label for="description">Description</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                            placeholder="Describe the session objectives, topics, and format..."
                        />
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label>Format</Label>
                            <Select v-model="form.format">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select format" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="fmt in sessionFormats" :key="fmt.value" :value="fmt.value">
                                        {{ fmt.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.format" />
                        </div>

                        <div class="space-y-2">
                            <Label for="target_audience">Target Audience</Label>
                            <Input id="target_audience" v-model="form.target_audience" placeholder="e.g. Health policy makers, researchers" />
                            <InputError :message="form.errors.target_audience" />
                        </div>

                        <div class="space-y-2">
                            <Label for="expected_participants">Expected Participants</Label>
                            <Input id="expected_participants" :model-value="form.expected_participants ?? undefined" @update:model-value="form.expected_participants = Number($event)" type="number" min="1" placeholder="e.g. 50" />
                            <InputError :message="form.errors.expected_participants" />
                        </div>

                        <div class="flex items-center gap-3 pt-6">
                            <Checkbox id="is_open" v-model="form.is_open" />
                            <Label for="is_open" class="cursor-pointer">Open to all conference attendees</Label>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Date &amp; Time</CardTitle>
                    <CardDescription>
                        Slots run across the conference dates<template v-if="conference?.start_date && conference?.end_date">
                        ({{ formatCalendarDate(conference.start_date, { month: 'long', day: 'numeric' }) }}
                        – {{ formatCalendarDate(conference.end_date, { month: 'long', day: 'numeric', year: 'numeric' }) }})</template>.
                        Changing your date and time needs approval from the partnerships team — the rest of this
                        form saves immediately.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="bg-muted/50 flex items-start gap-3 rounded-md border p-3">
                        <CalendarClock class="text-muted-foreground mt-0.5 h-4 w-4 shrink-0" />
                        <div class="space-y-1 text-sm">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium">
                                    {{ bookingOnly ? (boardBooking!.label || 'Scheduled') : (currentSlot?.slot_code ?? 'Not scheduled') }}
                                </span>
                                <Badge v-if="timePending" variant="outline" class="border-amber-400 text-amber-700 dark:text-amber-400">
                                    Pending approval
                                </Badge>
                                <Badge
                                    v-else-if="bookingOnly"
                                    variant="outline"
                                    class="border-green-500 text-green-700 dark:text-green-400"
                                >
                                    Scheduled by the programme team
                                </Badge>
                                <Badge v-else-if="approvedSlotId" variant="outline" class="border-green-500 text-green-700 dark:text-green-400">
                                    Approved
                                </Badge>
                            </div>
                            <p class="text-muted-foreground">
                                {{ bookingOnly ? boardBooking!.when : slotSchedule(currentSlot) }}
                            </p>
                            <p
                                v-if="boardBooking?.room"
                                class="text-muted-foreground flex items-center gap-1.5"
                            >
                                <MapPin class="h-3.5 w-3.5" />
                                {{ boardBooking.room }}
                            </p>
                            <p v-if="bookingOnly" class="text-muted-foreground text-xs">
                                The programme team placed this session directly, so it is not
                                tied to a slot below. Choosing a slot will request a move.
                            </p>
                            <p v-if="timePending && session.session_slot" class="text-muted-foreground text-xs">
                                Currently confirmed: {{ slotSchedule(session.session_slot) }} — this stays in place unless the request is approved.
                            </p>
                        </div>
                    </div>

                    <SessionSlotPicker
                        :slots="availableSlots ?? []"
                        v-model="form.session_slot_id"
                        :approved-slot-id="approvedSlotId"
                        :pending-slot-id="pendingSlotId"
                    />
                    <InputError :message="form.errors.session_slot_id" />

                    <div
                        v-if="!timePending && form.session_slot_id && form.session_slot_id !== approvedSlotId"
                        class="space-y-2 border-t pt-4"
                    >
                        <Label for="slot_reason">Reason for the time change (optional)</Label>
                        <textarea
                            id="slot_reason"
                            v-model="form.slot_reason"
                            rows="2"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                            placeholder="Helps the partnerships team decide, e.g. speaker availability changed..."
                        />
                        <InputError :message="form.errors.slot_reason" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Organizers &amp; Co-Hosts</CardTitle>
                    <CardDescription>Add session organizers and co-hosting organizations.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <div class="space-y-3">
                        <Label>Organizers</Label>
                        <div class="flex gap-2">
                            <Input v-model="newOrganizer" placeholder="Add organizer name" @keydown.enter.prevent="addOrganizer" />
                            <Button type="button" variant="outline" @click="addOrganizer">
                                <Plus class="h-4 w-4" />
                            </Button>
                        </div>
                        <div v-if="form.organizers.length > 0" class="flex flex-wrap gap-2">
                            <span
                                v-for="(org, i) in form.organizers"
                                :key="i"
                                class="bg-secondary inline-flex items-center gap-1 rounded-full px-3 py-1 text-sm"
                            >
                                {{ org }}
                                <button type="button" @click="removeOrganizer(i)" class="hover:text-destructive ml-1">
                                    <X class="h-3 w-3" />
                                </button>
                            </span>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <Label>Co-Hosts</Label>
                        <div class="flex gap-2">
                            <Input v-model="newCoHost" placeholder="Add co-host organization" @keydown.enter.prevent="addCoHost" />
                            <Button type="button" variant="outline" @click="addCoHost">
                                <Plus class="h-4 w-4" />
                            </Button>
                        </div>
                        <div v-if="form.co_hosts.length > 0" class="flex flex-wrap gap-2">
                            <span
                                v-for="(host, i) in form.co_hosts"
                                :key="i"
                                class="bg-secondary inline-flex items-center gap-1 rounded-full px-3 py-1 text-sm"
                            >
                                {{ host }}
                                <button type="button" @click="removeCoHost(i)" class="hover:text-destructive ml-1">
                                    <X class="h-3 w-3" />
                                </button>
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Special Requirements</CardTitle>
                    <CardDescription>Equipment, translation, seating, and catering needs.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-6 sm:grid-cols-2">
                        <div class="flex items-center gap-3">
                            <Checkbox
                                id="av_equipment"
                                v-model="form.special_requirements.av_equipment"
                            />
                            <Label for="av_equipment" class="cursor-pointer">AV Equipment Required</Label>
                        </div>

                        <div class="flex items-center gap-3">
                            <Checkbox
                                id="translation"
                                v-model="form.special_requirements.translation"
                            />
                            <Label for="translation" class="cursor-pointer">Translation Services</Label>
                        </div>

                        <div class="space-y-2">
                            <Label>Seating Type</Label>
                            <Select v-model="form.special_requirements.seating_type">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select seating" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="seat in seatingTypes" :key="seat.value" :value="seat.value">
                                        {{ seat.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="flex items-center gap-3">
                            <Checkbox
                                id="catering"
                                v-model="form.special_requirements.catering"
                            />
                            <Label for="catering" class="cursor-pointer">Catering Required</Label>
                        </div>
                    </div>
                </CardContent>
                <CardFooter class="flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        <Save class="mr-2 h-4 w-4" />
                        Update Session
                    </Button>
                </CardFooter>
            </Card>
        </form>
    </div>
</template>
