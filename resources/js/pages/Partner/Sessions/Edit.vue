<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Save, Plus, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import PartnerLayout from '@/layouts/PartnerLayout.vue';
import type { Partner, ConferenceSession, SessionFormat } from '@/types/partner';

defineOptions({ layout: PartnerLayout });

type SessionSlotOption = {
    id: number;
    slot_code: string;
    slot_category: string;
    track_label: string | null;
    day_index: number;
    date: string | null;
    time_label: string;
    default_format: string | null;
    default_room?: { id: number; name: string } | null;
};

const props = defineProps<{
    partner: Partner;
    session: ConferenceSession & { session_slot_id?: number | null };
    availableSlots?: SessionSlotOption[];
}>();

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
    session_slot_id: (props.session.session_slot_id ?? null) as number | null,
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

const slotsByDay = computed(() => {
    const groups: Record<number, SessionSlotOption[]> = {};
    (props.availableSlots ?? []).forEach((s) => {
        (groups[s.day_index] ||= []).push(s);
    });
    return groups;
});

function dayLabel(idx: number, slot: SessionSlotOption | undefined): string {
    if (slot?.date) {
        return `Day ${idx} — ${new Date(slot.date).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' })}`;
    }
    return `Day ${idx}`;
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
                            <Checkbox id="is_open" :checked="form.is_open" @update:checked="form.is_open = $event" />
                            <Label for="is_open" class="cursor-pointer">Open to all conference attendees</Label>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Preferred Session Slot</CardTitle>
                    <CardDescription>
                        Pick from the available slots in the AHAIC Session Slots &amp; Booths Scheduling Matrix.
                        Switching slots releases your current one back to the pool.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-if="!availableSlots?.length" class="text-muted-foreground text-sm">
                        No bookable slots are currently available.
                    </div>
                    <div v-for="(slots, idx) in slotsByDay" :key="idx" class="space-y-2">
                        <h4 class="text-sm font-semibold">{{ dayLabel(Number(idx), slots[0]) }}</h4>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            <label
                                v-for="slot in slots"
                                :key="slot.id"
                                class="flex cursor-pointer flex-col gap-1 rounded-md border p-3 text-sm transition hover:border-primary"
                                :class="form.session_slot_id === slot.id ? 'border-primary bg-primary/5' : 'border-input'"
                            >
                                <input type="radio" class="sr-only" :value="slot.id" v-model="form.session_slot_id" />
                                <span class="font-medium">{{ slot.slot_code }}</span>
                                <span class="text-muted-foreground text-xs">
                                    {{ slot.time_label }}<span v-if="slot.track_label"> · {{ slot.track_label }}</span>
                                </span>
                                <span v-if="slot.default_room" class="text-muted-foreground text-xs">
                                    Room: {{ slot.default_room.name }}
                                </span>
                                <span v-if="slot.default_format" class="text-muted-foreground text-xs capitalize">
                                    Setup: {{ slot.default_format }}
                                </span>
                            </label>
                        </div>
                    </div>
                    <button
                        v-if="form.session_slot_id"
                        type="button"
                        class="text-muted-foreground hover:text-foreground text-xs underline"
                        @click="form.session_slot_id = null"
                    >
                        Clear slot preference
                    </button>
                    <InputError :message="form.errors.session_slot_id" />
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
                                :checked="form.special_requirements.av_equipment"
                                @update:checked="form.special_requirements.av_equipment = $event"
                            />
                            <Label for="av_equipment" class="cursor-pointer">AV Equipment Required</Label>
                        </div>

                        <div class="flex items-center gap-3">
                            <Checkbox
                                id="translation"
                                :checked="form.special_requirements.translation"
                                @update:checked="form.special_requirements.translation = $event"
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
                                :checked="form.special_requirements.catering"
                                @update:checked="form.special_requirements.catering = $event"
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
