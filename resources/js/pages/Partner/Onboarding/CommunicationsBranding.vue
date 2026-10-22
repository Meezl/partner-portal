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
    /** Option key → label for "are you planning anything?", from the server. */
    planOptions: Record<string, string>;
}>();

/** Start from what was saved, so unticked options are present as false. */
function initialPlans(): CommsChecklist {
    const saved = props.branding?.ahaic_plans ?? {};
    const checklist: CommsChecklist = {};

    Object.keys(props.planOptions).forEach((key) => {
        checklist[key] = saved[key] === true;
    });

    checklist.other = typeof saved.other === 'string' && saved.other !== '' ? saved.other : null;

    return checklist;
}

const form = useForm({
    _method: 'put',
    ahaic_plans: initialPlans(),
    plans_summary: props.branding?.plans_summary ?? '',
    guideline_assets: [] as File[],
    material_assets: [] as File[],
});

/** Per-file upload errors come back as <field>.0, <field>.1, …; show them all. */
function uploadErrors(field: 'guideline_assets' | 'material_assets') {
    return Object.entries(form.errors)
        .filter(([key]) => key === field || key.startsWith(`${field}.`))
        .map(([, message]) => message);
}

const guidelineErrors = computed(() => uploadErrors('guideline_assets'));
const materialErrors = computed(() => uploadErrors('material_assets'));

/** Previously uploaded files, split by the question they answered. */
function savedAssets(category: string) {
    return (props.branding?.assets ?? []).filter(
        (asset) => (asset.category ?? 'materials') === category,
    );
}

const savedGuidelines = computed(() => savedAssets('guidelines'));
const savedMaterials = computed(() => savedAssets('materials'));

const uploadInstructions = [
    'Drag and drop your files here or click to browse.',
    'Maximum file size: 10 MB per file',
    'Accepted formats: PDF, PNG, JPG, SVG, ZIP',
];

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
                Share your brand guidelines and tell us what you are planning around AHAIC.
            </p>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Megaphone class="h-5 w-5" />
                    Brand Guidelines
                </CardTitle>
                <CardDescription>
                    Please provide the brand guidelines we should follow for the conference.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-8">
                <div class="space-y-3">
                    <Label>Upload your branding guidelines for promotional plans</Label>
                    <FileUpload
                        accept=".zip,.png,.jpg,.jpeg,.pdf,.svg"
                        multiple
                        :instructions="uploadInstructions"
                        @change-multiple="(files: File[]) => (form.guideline_assets = files)"
                    />
                    <InputError v-for="(message, i) in guidelineErrors" :key="i" :message="message" />

                    <div v-if="savedGuidelines.length">
                        <p class="text-muted-foreground mb-2 text-sm font-medium">Already uploaded:</p>
                        <ul class="space-y-1">
                            <li v-for="(asset, i) in savedGuidelines" :key="i" class="text-sm">
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

                <div class="space-y-3">
                    <div>
                        <Label>Upload any other branding materials</Label>
                        <p class="text-muted-foreground mt-1 text-sm">
                            An abridged set is fine — logos, templates, anything else we should use.
                        </p>
                    </div>
                    <FileUpload
                        accept=".zip,.png,.jpg,.jpeg,.pdf,.svg"
                        multiple
                        :instructions="uploadInstructions"
                        @change-multiple="(files: File[]) => (form.material_assets = files)"
                    />
                    <InputError v-for="(message, i) in materialErrors" :key="i" :message="message" />

                    <div v-if="savedMaterials.length">
                        <p class="text-muted-foreground mb-2 text-sm font-medium">Already uploaded:</p>
                        <ul class="space-y-1">
                            <li v-for="(asset, i) in savedMaterials" :key="i" class="text-sm">
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
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Are you planning anything around AHAIC?</CardTitle>
                <CardDescription>
                    Tick everything you are working on, then tell us about it below.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                <div class="space-y-3">
                    <ChecklistGroup
                        v-model="form.ahaic_plans"
                        :options="planOptions"
                        id-prefix="plans"
                        other-placeholder="Tell us what else you are planning"
                    />
                    <InputError :message="form.errors.ahaic_plans" />
                </div>

                <div class="space-y-2">
                    <Label for="plans_summary">Please share one or two paragraphs on the above</Label>
                    <textarea
                        id="plans_summary"
                        v-model="form.plans_summary"
                        rows="6"
                        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                        placeholder="What you are planning, who it is with, and how it connects to the conference..."
                    />
                    <InputError :message="form.errors.plans_summary" />
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
