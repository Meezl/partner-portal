<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Save, Plus, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import SessionSlotPicker from '@/components/shared/SessionSlotPicker.vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select';
import PartnerLayout from '@/layouts/PartnerLayout.vue';
import { formatCalendarDate } from '@/lib/utils';
import type { Conference, Partner, SessionFormat, SessionSlot } from '@/types/partner';

defineOptions({ layout: PartnerLayout });

defineProps<{
    partner: Partner;
    conference?: Conference | null;
    availableSlots?: SessionSlot[];
    /** Headcount bands a session picks from, from the server. */
    participantRanges: { value: string; label: string }[];
}>();

const form = useForm({
    title: '',
    description: '',
    format: '' as SessionFormat | '',
    co_hosts: [] as string[],
    expected_participants: null as string | null,
    session_slot_id: null as number | null,
    slot_reason: '',
    special_requirements: {
        av_equipment: false,
        translation: false,
        catering: false,
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
    form.post('/partner/sessions');
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
            <h1 class="font-heading text-3xl font-bold tracking-tight">Create Session</h1>
            <p class="text-muted-foreground mt-1">Add a new conference session for your partnership.</p>
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
                        Choose a slot from the AHAIC scheduling matrix. Slots run across the conference
                        dates<template v-if="conference?.start_date && conference?.end_date">
                        ({{ formatCalendarDate(conference.start_date, { month: 'long', day: 'numeric' }) }}
                        – {{ formatCalendarDate(conference.end_date, { month: 'long', day: 'numeric', year: 'numeric' }) }})</template>.
                        Your choice is sent to the partnerships team for approval and is not confirmed until they sign off.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <SessionSlotPicker
                        :slots="availableSlots ?? []"
                        v-model="form.session_slot_id"
                        allow-clear
                    />
                    <InputError :message="form.errors.session_slot_id" />

                    <div v-if="form.session_slot_id" class="space-y-2 border-t pt-4">
                        <Label for="slot_reason">Any other additional comment (optional)</Label>
                        <textarea
                            id="slot_reason"
                            v-model="form.slot_reason"
                            rows="2"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                            placeholder="Why this slot? e.g. speaker availability, co-host travel dates..."
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
                        Create Session
                    </Button>
                </CardFooter>
            </Card>
        </form>
    </div>
</template>
