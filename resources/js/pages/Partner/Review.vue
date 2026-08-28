<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Building2,
    CalendarDays,
    Megaphone,
    Users,
    Send,
    AlertTriangle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import BlockedActionHint from '@/components/shared/BlockedActionHint.vue';
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import PartnerLayout from '@/layouts/PartnerLayout.vue';
import { canFinalizeSubmission, getIncompleteOnboardingSections } from '@/lib/onboarding-workflow.js';
import type { BrandingRequirement, Partner, OnboardingProgress as OnboardingProgressType } from '@/types/partner';

defineOptions({ layout: PartnerLayout });

const props = defineProps<{
    partner: Partner;
    progress: OnboardingProgressType;
}>();

const showSubmitDialog = ref(false);

function submitAll() {
    showSubmitDialog.value = false;
    router.post('/partner/submit');
}

function formatLabel(value: string) {
    return value.charAt(0).toUpperCase() + value.slice(1).replace(/_/g, ' ');
}

const socialMedia = props.partner.social_media ?? {};
const sessions = props.partner.sessions ?? [];
const contacts = props.partner.contacts ?? [];
const branding = (props.partner.brandingRequirement ?? null) as BrandingRequirement | null;
const canSubmit = computed(() =>
    canFinalizeSubmission(props.progress, sessions.length),
);
const missingSections = computed(() =>
    getIncompleteOnboardingSections(props.progress),
);

/** Why final submission is unavailable, named specifically. */
const submitBlockers = computed(() => {
    const blockers: string[] = [];

    missingSections.value.forEach((section: string) => {
        blockers.push(`Complete the ${String(section).replace(/_/g, ' ')} section.`);
    });

    if (sessions.length === 0) {
        blockers.push('Add at least one session.');
    }

    return blockers;
});
</script>

<template>
    <div class="space-y-8">
        <div>
            <h1 class="font-heading text-3xl font-bold tracking-tight">Review Submission</h1>
            <p class="text-muted-foreground mt-1">Review all your submitted information before final submission.</p>
        </div>

        <Card class="border-amber-200 bg-amber-50">
            <CardContent class="flex items-start gap-3 py-4">
                <AlertTriangle class="mt-0.5 h-5 w-5 text-amber-600" />
                <div>
                    <p class="font-medium text-amber-800">Important Notice</p>
                    <p class="text-sm text-amber-700">
                        After final submission, your data will be locked for review by the conference team. You will not be able to make changes unless a change request is approved.
                    </p>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Megaphone class="h-5 w-5" />
                    Communications &amp; Branding
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div v-if="branding?.requirements">
                    <p class="text-muted-foreground text-sm">Requirements</p>
                    <p class="mt-1 text-sm">{{ branding.requirements }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <p class="text-muted-foreground text-sm">Media Contact</p>
                        <p class="font-medium">{{ branding?.media_contact_name ?? 'Not provided' }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-sm">Media Email</p>
                        <p class="font-medium">{{ branding?.media_contact_email ?? 'Not provided' }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-sm">Media Phone</p>
                        <p class="font-medium">{{ branding?.media_contact_phone ?? 'Not provided' }}</p>
                    </div>
                </div>

                <div v-if="branding?.assets && branding.assets.length > 0">
                    <p class="text-muted-foreground mb-2 text-sm">Uploaded Assets</p>
                    <div class="flex flex-wrap gap-2">
                        <Badge v-for="(asset, index) in branding.assets" :key="index" variant="outline">
                            Asset {{ index + 1 }}
                        </Badge>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Building2 class="h-5 w-5" />
                    Organization Profile
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-muted-foreground text-sm">Organization Name</p>
                        <p class="font-medium">{{ partner.organization_name }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-sm">Contact Person</p>
                        <p class="font-medium">{{ partner.contact_person }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-sm">Email</p>
                        <p class="font-medium">{{ partner.email }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-sm">Phone</p>
                        <p class="font-medium">{{ partner.phone ?? 'Not provided' }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-sm">Expected Participants</p>
                        <p class="font-medium">{{ partner.number_of_participants ?? 'Not specified' }}</p>
                    </div>
                </div>

                <div v-if="partner.description">
                    <p class="text-muted-foreground text-sm">Description</p>
                    <p class="mt-1 text-sm">{{ partner.description }}</p>
                </div>

                <div v-if="Object.keys(socialMedia).length > 0">
                    <p class="text-muted-foreground mb-2 text-sm">Social Media</p>
                    <div class="flex flex-wrap gap-2">
                        <Badge v-for="(url, platform) in socialMedia" :key="platform" variant="outline">
                            {{ formatLabel(String(platform)) }}: {{ url }}
                        </Badge>
                    </div>
                </div>

                <div v-if="partner.exhibition_preferences">
                    <p class="text-muted-foreground text-sm">Exhibition Preferences</p>
                    <p class="mt-1 text-sm">{{ partner.exhibition_preferences }}</p>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <CalendarDays class="h-5 w-5" />
                    Sessions ({{ sessions.length }})
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="sessions.length === 0" class="text-muted-foreground py-4 text-center text-sm">No sessions submitted.</div>
                <div v-else class="space-y-4">
                    <div v-for="session in sessions" :key="session.id" class="rounded-lg border p-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <h4 class="font-medium">{{ session.title }}</h4>
                                <div class="mt-1 flex items-center gap-2">
                                    <Badge variant="outline">{{ formatLabel(session.format) }}</Badge>
                                    <span v-if="session.expected_participants" class="text-muted-foreground text-xs">
                                        {{ session.expected_participants }} expected participants
                                    </span>
                                </div>
                            </div>
                            <Badge :variant="session.is_open ? 'default' : 'secondary'">
                                {{ session.is_open ? 'Open' : 'Closed' }}
                            </Badge>
                        </div>
                        <p v-if="session.description" class="text-muted-foreground mt-2 text-sm">{{ session.description }}</p>
                        <div v-if="session.organizers && session.organizers.length > 0" class="mt-2">
                            <span class="text-muted-foreground text-xs">Organizers: </span>
                            <span class="text-xs">{{ session.organizers.join(', ') }}</span>
                        </div>
                        <div v-if="session.co_hosts && session.co_hosts.length > 0" class="mt-1">
                            <span class="text-muted-foreground text-xs">Co-hosts: </span>
                            <span class="text-xs">{{ session.co_hosts.join(', ') }}</span>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Users class="h-5 w-5" />
                    Contacts ({{ contacts.length }})
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="contacts.length === 0" class="text-muted-foreground py-4 text-center text-sm">No contacts added.</div>
                <div v-else class="space-y-3">
                    <div v-for="contact in contacts" :key="contact.id" class="flex items-center justify-between rounded-lg border p-3">
                        <div>
                            <p class="font-medium">{{ contact.name }}</p>
                            <p class="text-muted-foreground text-xs">{{ contact.email }} {{ contact.phone ? '| ' + contact.phone : '' }}</p>
                        </div>
                        <Badge variant="outline">{{ formatLabel(contact.role) }}</Badge>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Separator />

        <Card>
            <CardContent class="flex items-center justify-between py-6">
                <div>
                    <h3 class="font-semibold">Ready to Submit?</h3>
                    <p class="text-muted-foreground text-sm">
                        <span v-if="canSubmit">
                            This will lock your submission for review. You can request changes afterwards if needed.
                        </span>
                        <span v-else>
                            Complete all onboarding sections and add at least one session before final submission.
                        </span>
                    </p>
                    <p v-if="missingSections.length > 0" class="mt-1 text-xs text-amber-700">
                        Missing sections: {{ missingSections.join(', ') }}
                    </p>
                </div>
                <BlockedActionHint :reasons="submitBlockers" class="mb-3" />
                <Button size="lg" :disabled="!canSubmit" @click="showSubmitDialog = true">
                    <Send class="mr-2 h-4 w-4" />
                    Submit All
                </Button>
            </CardContent>
        </Card>

        <ConfirmDialog
            :open="showSubmitDialog"
            title="Confirm Final Submission"
            description="By submitting, all your partnership details will be locked and sent for review. You will not be able to edit any information after this point. Change requests can be submitted if modifications are needed."
            @confirm="submitAll"
            @cancel="showSubmitDialog = false"
        />
    </div>
</template>
