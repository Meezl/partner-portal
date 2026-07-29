<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Save, Megaphone, Upload } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import FileUpload from '@/components/shared/FileUpload.vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PartnerLayout from '@/layouts/PartnerLayout.vue';
import type { Partner, BrandingRequirement } from '@/types/partner';

defineOptions({ layout: PartnerLayout });

const props = defineProps<{
    partner: Partner;
    branding: BrandingRequirement | null;
}>();

const form = useForm({
    requirements: props.branding?.requirements ?? '',
    media_contact_name: props.branding?.media_contact_name ?? '',
    media_contact_email: props.branding?.media_contact_email ?? '',
    media_contact_phone: props.branding?.media_contact_phone ?? '',
    assets: null as File | null,
});

function handleAssetSelect(file: File) {
    form.assets = file;
}

function submit() {
    form.put('/partner/onboarding/communications', {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="space-y-8">
        <div>
            <h1 class="font-heading text-3xl font-bold tracking-tight">Communications &amp; Branding</h1>
            <p class="text-muted-foreground mt-1">Provide your branding requirements and media contact information.</p>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Megaphone class="h-5 w-5" />
                    Branding Requirements
                </CardTitle>
                <CardDescription>Describe how your brand should be represented at the conference.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                <div class="space-y-2">
                    <Label for="requirements">Requirements</Label>
                    <textarea
                        id="requirements"
                        v-model="form.requirements"
                        rows="4"
                        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                        placeholder="Describe your branding requirements, style guidelines, color preferences, etc."
                    />
                    <InputError :message="form.errors.requirements" />
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Branding Assets</CardTitle>
                <CardDescription>Upload logos, banners, or other branding materials (ZIP, PNG, PDF accepted).</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="space-y-4">
                    <FileUpload accept=".zip,.png,.jpg,.jpeg,.pdf,.svg" @change="handleAssetSelect" />
                    <InputError :message="form.errors.assets" />

                    <div v-if="branding?.assets && branding.assets.length > 0">
                        <p class="text-muted-foreground mb-2 text-sm font-medium">Previously uploaded assets:</p>
                        <ul class="space-y-1">
                            <li v-for="(asset, i) in branding.assets" :key="i" class="text-muted-foreground flex items-center gap-2 text-sm">
                                <Upload class="h-3 w-3" />
                                {{ asset }}
                            </li>
                        </ul>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Media Contact</CardTitle>
                <CardDescription>Provide a point of contact for media and communications.</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="space-y-2">
                        <Label for="media_contact_name">Name</Label>
                        <Input id="media_contact_name" v-model="form.media_contact_name" placeholder="Full name" />
                        <InputError :message="form.errors.media_contact_name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="media_contact_email">Email</Label>
                        <Input id="media_contact_email" type="email" v-model="form.media_contact_email" placeholder="email@example.com" />
                        <InputError :message="form.errors.media_contact_email" />
                    </div>

                    <div class="space-y-2">
                        <Label for="media_contact_phone">Phone</Label>
                        <Input id="media_contact_phone" v-model="form.media_contact_phone" placeholder="+254 700 000 000" />
                        <InputError :message="form.errors.media_contact_phone" />
                    </div>
                </div>
            </CardContent>
            <CardFooter class="flex justify-end">
                <Button @click="submit" :disabled="form.processing">
                    <Save class="mr-2 h-4 w-4" />
                    Save Communications &amp; Branding
                </Button>
            </CardFooter>
        </Card>
    </div>
</template>
