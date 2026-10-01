<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Building2,
    CalendarDays,
    Image as ImageIcon,
    Megaphone,
    Users,
    Send,
    AlertTriangle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import BlockedActionHint from '@/components/shared/BlockedActionHint.vue';
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import FileLink from '@/components/shared/FileLink.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import PartnerLayout from '@/layouts/PartnerLayout.vue';
import { filledSocialLinks, tickedChecklistLabels } from '@/lib/checklists.js';
import { canFinalizeSubmission, getIncompleteOnboardingSections } from '@/lib/onboarding-workflow.js';
import type { BrandingRequirement, Partner, OnboardingProgress as OnboardingProgressType } from '@/types/partner';

defineOptions({ layout: PartnerLayout });

const props = defineProps<{
    partner: Partner;
    progress: OnboardingProgressType;
    /** Option key → label for the exhibition checklist, from the server. */
    /** Option key → label for the communications checklist, from the server. */
    commsOptions: Record<string, string>;
    /** Role key → label, so the summary matches the Contacts form. */
    contactRoles: Record<string, string>;
}>();


const showSubmitDialog = ref(false);

function submitAll() {
    showSubmitDialog.value = false;
    router.post('/partner/submit');
}

function formatLabel(value: string) {
    return value.charAt(0).toUpperCase() + value.slice(1).replace(/_/g, ' ');
}

/**
 * The name to save the logo under. Uploads made before the name was recorded
 * fall back to the hashed storage filename.
 */
const logoName = computed(() => {
    if (props.partner.logo_name) {
        return props.partner.logo_name;
    }

    const path = props.partner.logo_path ?? '';

    return decodeURIComponent(path.split('/').pop() || 'logo');
});
const sessions = props.partner.sessions ?? [];
const contacts = props.partner.contacts ?? [];
const branding = (props.partner.branding_requirement ?? null) as BrandingRequirement | null;
const commsRequirements = computed(() =>
    tickedChecklistLabels(props.commsOptions, branding?.comms_checklist ?? null),
);
const socialLinks = computed(() => filledSocialLinks(props.partner.social_media));
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
                <div v-if="commsRequirements.length > 0">
                    <p class="text-muted-foreground mb-2 text-sm">Requested</p>
                    <div class="flex flex-wrap gap-2">
                        <Badge v-for="(label, index) in commsRequirements" :key="index" variant="outline">
                            {{ label }}
                        </Badge>
                    </div>
                </div>

                <div v-if="branding?.requirements">
                    <p class="text-muted-foreground text-sm">Additional comments</p>
                    <p class="mt-1 text-sm">{{ branding.requirements }}</p>
                </div>

                <div v-if="branding?.assets && branding.assets.length > 0">
                    <p class="text-muted-foreground mb-2 text-sm">
                        Uploaded assets ({{ branding.assets.length }})
                    </p>
                    <ul class="space-y-1">
                        <li v-for="(asset, index) in branding.assets" :key="index">
                            <FileLink :name="asset.name" :url="asset.url" />
                        </li>
                    </ul>
                </div>

                <p
                    v-if="commsRequirements.length === 0 && !branding?.requirements && !branding?.assets?.length"
                    class="text-muted-foreground text-sm"
                >
                    Nothing added yet — tell the communications team what you need, and upload your
                    branding assets.
                </p>
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
                <div class="flex items-start gap-4">
                    <div class="bg-muted flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border">
                        <img
                            v-if="partner.logo_path"
                            :src="partner.logo_path"
                            :alt="`${partner.organization_name} logo`"
                            class="h-full w-full object-contain"
                        />
                        <ImageIcon v-else class="text-muted-foreground h-7 w-7" />
                    </div>
                    <div class="min-w-0 flex-1 space-y-1">
                        <p class="text-muted-foreground text-sm">Logo</p>
                        <FileLink
                            v-if="partner.logo_path"
                            :name="logoName"
                            :url="partner.logo_path"
                        />
                        <p v-else class="text-sm">No logo uploaded.</p>
                    </div>
                </div>

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
                </div>

                <div v-if="partner.description">
                    <p class="text-muted-foreground text-sm">Description</p>
                    <p class="mt-1 text-sm">{{ partner.description }}</p>
                </div>

                <div v-if="socialLinks.length > 0">
                    <p class="text-muted-foreground mb-2 text-sm">Social Media</p>
                    <ul class="space-y-1 text-sm">
                        <li v-for="[platform, url] in socialLinks" :key="platform">
                            <span class="text-muted-foreground">{{ formatLabel(platform) }}:</span>
                            <a :href="url" target="_blank" rel="noopener" class="ml-1 underline underline-offset-2">
                                {{ url }}
                            </a>
                        </li>
                    </ul>
                </div>

                <div v-if="partner.exhibition_preferences">
                    <p class="text-muted-foreground text-sm">Additional comments</p>
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
                        </div>
                        <p v-if="session.description" class="text-muted-foreground mt-2 text-sm">{{ session.description }}</p>
                        <div v-if="session.co_hosts && session.co_hosts.length > 0" class="mt-2">
                            <span class="text-muted-foreground text-xs">Co-hosts / partners: </span>
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
                            <p v-if="contact.designation" class="text-muted-foreground text-xs">{{ contact.designation }}</p>
                            <p class="text-muted-foreground text-xs">{{ contact.email }} {{ contact.phone ? '| ' + contact.phone : '' }}</p>
                        </div>
                        <Badge variant="outline">{{ contactRoles[contact.role] ?? formatLabel(contact.role) }}</Badge>
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
