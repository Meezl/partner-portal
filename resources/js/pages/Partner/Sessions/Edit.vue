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
    /** Headcount bands a session picks from, from the server. */
    participantRanges: { value: string; label: string }[];
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
    co_hosts: (props.session.co_hosts ?? []) as string[],
    expected_participants: props.session.expected_participants,
    session_slot_id: (props.session.requested_session_slot_id ?? props.session.session_slot_id ?? null) as number | null,
    slot_reason: '',
    special_requirements: {
        av_equipment: (specialReqs.av_equipment as boolean) ?? false,
        translation: (specialReqs.translation as boolean) ?? false,
        catering: (specialReqs.catering as boolean) ?? false,
    },
});

const descriptionWordCount = computed(() => {
    const text = form.description.trim();

    return text ? text.split(/\s+/).length : 0;
});

const descriptionTooLong = computed(() => descriptionWordCount.value > 150);

const newCoHost = ref('');

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
    { value: 'roundtable', label: 'Roundtable' },
    { value: 'panel', label: 'Panel Discussion' },
    { value: 'fireside_chat', label: 'Fireside Chat' },
    { value: 'keynote', label: 'Keynote / Featured Address' },
    { value: 'workshop', label: 'Workshop / Masterclass' },
    { value: 'interactive_dialogue', label: 'Interactive Dialogue' },
    { value: 'live_studio', label: 'Live Studio Session' },
    { value: 'stand_up', label: 'Stand-up Session' },
    { value: 'breakout', label: 'Breakout Session' },
    { value: 'networking', label: 'Networking / Reception' },
    { value: 'cocktail', label: 'Cocktail' },
    { value: 'showcase', label: 'Product / Solution Showcase' },
    { value: 'other', label: 'Other' },
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
                        <p class="text-muted-foreground text-xs">
                            Max 150 words. This will go to the website, the mobile app and any other
                            session related material.
                        </p>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                            placeholder="Describe the session objectives, topics, and format..."
                        />
                        <div class="flex items-center justify-between">
                            <InputError :message="form.errors.description" />
                            <span class="text-xs" :class="descriptionTooLong ? 'text-red-500' : 'text-muted-foreground'">
                                {{ descriptionWordCount }} / 150 words
                            </span>
                        </div>
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
                            <Label>Expected Participants</Label>
                            <Select v-model="form.expected_participants">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select a range" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="range in participantRanges" :key="range.value" :value="range.value">
                                        {{ range.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.expected_participants" />
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
                        <Label for="slot_reason">Any other additional comment (optional)</Label>
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
                    <CardTitle>Co-Hosts / Partners</CardTitle>
                    <CardDescription>Add the organizations co-hosting or partnering on this session.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <div class="space-y-3">
                        <Label>Co-Hosts / Partners</Label>
                        <div class="flex gap-2">
                            <Input v-model="newCoHost" placeholder="Add a co-host or partner organization" @keydown.enter.prevent="addCoHost" />
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
                    <CardDescription>Equipment, translation, and catering needs.</CardDescription>
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
