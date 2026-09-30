<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Save, Megaphone, Upload } from 'lucide-vue-next';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import ChecklistGroup from '@/components/shared/ChecklistGroup.vue';
import FileUpload from '@/components/shared/FileUpload.vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import PartnerLayout from '@/layouts/PartnerLayout.vue';
import type { Partner, BrandingRequirement, CommsChecklist } from '@/types/partner';

defineOptions({ layout: PartnerLayout });

const props = defineProps<{
    partner: Partner;
    branding: BrandingRequirement | null;
    /** Option key → label for the communications checklist, from the server. */
    commsOptions: Record<string, string>;
}>();

/** Start from what was saved, so unticked options are present as false. */
function initialCommsChecklist(): CommsChecklist {
    const saved = props.branding?.comms_checklist ?? {};
    const checklist: CommsChecklist = {};

    Object.keys(props.commsOptions).forEach((key) => {
        checklist[key] = saved[key] === true;
    });

    checklist.other = typeof saved.other === 'string' && saved.other !== '' ? saved.other : null;

    return checklist;
}

const form = useForm({
    _method: 'put',
    requirements: props.branding?.requirements ?? '',
    comms_checklist: initialCommsChecklist(),
    assets: [] as File[],
});

function handleAssetSelect(files: File[]) {
    form.assets = files;
}

/** Per-file upload errors come back as assets.0, assets.1, …; show them all. */
const assetErrors = computed(() =>
    Object.entries(form.errors)
        .filter(([key]) => key === 'assets' || key.startsWith('assets.'))
        .map(([, message]) => message),
);


function submit() {
    form.post('/partner/onboarding/communications', {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="space-y-8">
        <div>
            <h1 class="font-heading text-3xl font-bold tracking-tight">Communications &amp; Branding</h1>
            <p class="text-muted-foreground mt-1">
                Tell the communications team what you need, and upload your branding assets.
            </p>
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
                <div class="space-y-3">
                    <div>
                        <Label>What do you need from communications?</Label>
                        <p class="text-muted-foreground mt-1 text-sm">
                            Tick everything that applies. The communications team works from this list.
                        </p>
                    </div>
                    <ChecklistGroup
                        v-model="form.comms_checklist"
                        :options="commsOptions"
                        id-prefix="comms"
                        other-placeholder="Tell us what else you need"
                    />
                    <InputError :message="form.errors.comms_checklist" />
                </div>

                <div class="space-y-2">
                    <Label for="requirements">Any other additional comment</Label>
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
                <CardDescription>
                    Upload logos, banners, or other branding materials — select as many files as you
                    like in one go (ZIP, PNG, JPG, SVG, PDF accepted).
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="space-y-4">
                    <FileUpload
                        accept=".zip,.png,.jpg,.jpeg,.pdf,.svg"
                        multiple
                        @change-multiple="handleAssetSelect"
                    />
                    <InputError v-for="(message, i) in assetErrors" :key="i" :message="message" />

                    <div v-if="branding?.assets && branding.assets.length > 0">
                        <p class="text-muted-foreground mb-2 text-sm font-medium">Previously uploaded assets:</p>
                        <ul class="space-y-1">
                            <li v-for="(asset, i) in branding.assets" :key="i" class="text-sm">
                                <a
                                    :href="asset.url"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-muted-foreground hover:text-foreground flex items-center gap-2 underline"
                                >
                                    <Upload class="h-3 w-3" />
                                    {{ asset.name }}
                                </a>
                            </li>
                        </ul>
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
