<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Save, Globe, Twitter, Linkedin, Facebook, Image } from 'lucide-vue-next';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import FileUpload from '@/components/shared/FileUpload.vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PartnerLayout from '@/layouts/PartnerLayout.vue';
import type { Partner } from '@/types/partner';

defineOptions({ layout: PartnerLayout });

const props = defineProps<{
    partner: Partner;
}>();

const form = useForm({
    logo: null as File | null,
    description: props.partner.description ?? '',
    social_media: {
        twitter: props.partner.social_media?.twitter ?? '',
        linkedin: props.partner.social_media?.linkedin ?? '',
        facebook: props.partner.social_media?.facebook ?? '',
        website: props.partner.social_media?.website ?? '',
    },
    number_of_participants: props.partner.number_of_participants ?? null,
    exhibition_preferences: props.partner.exhibition_preferences ?? '',
});

const wordCount = computed(() => {
    const text = form.description.trim();

    if (!text) {
return 0;
}

    return text.split(/\s+/).length;
});

const wordCountExceeded = computed(() => wordCount.value > 100);

function handleLogoSelect(file: File) {
    form.logo = file;
}

function updateParticipants(value: string | number) {
    if (value === '' || value === null || value === undefined) {
        form.number_of_participants = null;

        return;
    }

    const numericValue = Number(value);

    form.number_of_participants = Number.isNaN(numericValue)
        ? null
        : numericValue;
}

function submit() {
    form.put('/partner/onboarding/organization', {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="space-y-8">
        <div>
            <h1 class="font-heading text-3xl font-bold tracking-tight">Organization Profile</h1>
            <p class="text-muted-foreground mt-1">Provide details about your organization for the conference materials.</p>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Logo</CardTitle>
                <CardDescription>Upload your organization logo. Recommended size: 400x400px, PNG or SVG format.</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="flex items-start gap-6">
                    <div v-if="partner.logo_path" class="bg-muted flex h-24 w-24 items-center justify-center overflow-hidden rounded-lg border">
                        <img :src="partner.logo_path" alt="Organization logo" class="h-full w-full object-contain" />
                    </div>
                    <div v-else class="bg-muted flex h-24 w-24 items-center justify-center rounded-lg border">
                        <Image class="text-muted-foreground h-8 w-8" />
                    </div>
                    <div class="flex-1">
                        <FileUpload accept=".png,.jpg,.jpeg,.svg" @change="handleLogoSelect" />
                        <InputError :message="form.errors.logo" class="mt-2" />
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Description</CardTitle>
                <CardDescription>A brief description of your organization (max 100 words).</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="space-y-2">
                    <textarea
                        v-model="form.description"
                        rows="4"
                        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                        placeholder="Describe your organization..."
                    />
                    <div class="flex items-center justify-between">
                        <InputError :message="form.errors.description" />
                        <span class="text-xs" :class="wordCountExceeded ? 'text-red-500' : 'text-muted-foreground'">
                            {{ wordCount }} / 100 words
                        </span>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Social Media & Website</CardTitle>
                <CardDescription>Provide links to your organization's online presence.</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label class="flex items-center gap-2">
                            <Globe class="h-4 w-4" /> Website
                        </Label>
                        <Input v-model="form.social_media.website" placeholder="https://example.com" />
                        <InputError :message="form.errors['social_media.website']" />
                    </div>
                    <div class="space-y-2">
                        <Label class="flex items-center gap-2">
                            <Twitter class="h-4 w-4" /> Twitter / X
                        </Label>
                        <Input v-model="form.social_media.twitter" placeholder="https://twitter.com/yourorg" />
                        <InputError :message="form.errors['social_media.twitter']" />
                    </div>
                    <div class="space-y-2">
                        <Label class="flex items-center gap-2">
                            <Linkedin class="h-4 w-4" /> LinkedIn
                        </Label>
                        <Input v-model="form.social_media.linkedin" placeholder="https://linkedin.com/company/yourorg" />
                        <InputError :message="form.errors['social_media.linkedin']" />
                    </div>
                    <div class="space-y-2">
                        <Label class="flex items-center gap-2">
                            <Facebook class="h-4 w-4" /> Facebook
                        </Label>
                        <Input v-model="form.social_media.facebook" placeholder="https://facebook.com/yourorg" />
                        <InputError :message="form.errors['social_media.facebook']" />
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Additional Details</CardTitle>
            </CardHeader>
            <CardContent class="space-y-6">
                <div class="space-y-2">
                    <Label for="number_of_participants">Expected Number of Participants</Label>
                    <Input id="number_of_participants" :model-value="form.number_of_participants ?? undefined" @update:model-value="updateParticipants" type="number" min="1" placeholder="e.g. 25" />
                    <InputError :message="form.errors.number_of_participants" />
                </div>

                <div class="space-y-2">
                    <Label for="exhibition_preferences">Exhibition Preferences</Label>
                    <textarea
                        id="exhibition_preferences"
                        v-model="form.exhibition_preferences"
                        rows="3"
                        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                        placeholder="Describe your exhibition space requirements, booth preferences, etc."
                    />
                    <InputError :message="form.errors.exhibition_preferences" />
                </div>
            </CardContent>
            <CardFooter class="flex justify-end">
                <Button @click="submit" :disabled="form.processing || wordCountExceeded">
                    <Save class="mr-2 h-4 w-4" />
                    Save Organization Profile
                </Button>
            </CardFooter>
        </Card>
    </div>
</template>
