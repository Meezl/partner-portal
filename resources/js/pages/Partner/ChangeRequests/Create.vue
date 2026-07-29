<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Send } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select';
import PartnerLayout from '@/layouts/PartnerLayout.vue';
import type { ConferenceSession, ChangeRequestType } from '@/types/partner';

defineOptions({ layout: PartnerLayout });

const props = defineProps<{
    sessions: ConferenceSession[];
}>();

const form = useForm({
    conference_session_id: '',
    type: '' as ChangeRequestType | '',
    requested_value: '',
    reason: '',
});

function submit() {
    form.post('/partner/change-requests');
}

const changeTypes: { value: ChangeRequestType; label: string }[] = [
    { value: 'time', label: 'Time Change' },
    { value: 'room', label: 'Room Change' },
    { value: 'session_details', label: 'Session Details' },
    { value: 'other', label: 'Other' },
];
</script>

<template>
    <div class="space-y-8">
        <div>
            <h1 class="font-heading text-3xl font-bold tracking-tight">New Change Request</h1>
            <p class="text-muted-foreground mt-1">Submit a request to change session scheduling or details.</p>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Request Details</CardTitle>
                <CardDescription>Provide details about the change you would like to request.</CardDescription>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-6">
                    <div class="space-y-2">
                        <Label>Session</Label>
                        <Select v-model="form.conference_session_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Select a session" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="session in sessions" :key="session.id" :value="session.id.toString()">
                                    {{ session.title }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.conference_session_id" />
                    </div>

                    <div class="space-y-2">
                        <Label>Type of Change</Label>
                        <Select v-model="form.type">
                            <SelectTrigger>
                                <SelectValue placeholder="Select change type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="ct in changeTypes" :key="ct.value" :value="ct.value">
                                    {{ ct.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.type" />
                    </div>

                    <div class="space-y-2">
                        <Label for="requested_value">Requested Change</Label>
                        <textarea
                            id="requested_value"
                            v-model="form.requested_value"
                            rows="3"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                            placeholder="Describe the change you would like (e.g., preferred time, room type, details to update)..."
                        />
                        <InputError :message="form.errors.requested_value" />
                    </div>

                    <div class="space-y-2">
                        <Label for="reason">Reason</Label>
                        <textarea
                            id="reason"
                            v-model="form.reason"
                            rows="3"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                            placeholder="Explain why this change is needed..."
                        />
                        <InputError :message="form.errors.reason" />
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-end">
                <Button @click="submit" :disabled="form.processing">
                    <Send class="mr-2 h-4 w-4" />
                    Submit Request
                </Button>
            </CardFooter>
        </Card>
    </div>
</template>
