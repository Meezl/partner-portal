<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import {
    Save,
    Send,
    ChevronRight,
    CheckCircle2,
    FileEdit,
    LayoutDashboard,
    Info,
} from 'lucide-vue-next';
import { ref, watch, computed, nextTick } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import PackageCard from '@/components/partner/PackageCard.vue';
import BlockedActionHint from '@/components/shared/BlockedActionHint.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
    CardFooter,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import PartnerLayout from '@/layouts/PartnerLayout.vue';
import type { Partner, SponsorshipPackage } from '@/types/partner';

defineOptions({ layout: PartnerLayout });

const props = defineProps<{
    packages: SponsorshipPackage[];
    partner?: Partner | null;
    conference?: { id: number; name: string } | null;
}>();

const isDraft = computed(() => props.partner?.status === 'draft');
const isSubmitted = computed(
    () => props.partner?.status === 'interest_submitted',
);
const isRejected = computed(() => props.partner?.status === 'rejected');
const hasExistingPartner = computed(() => !!props.partner);

const selectedPackageId = ref<number | null>(
    props.partner?.packages?.[0]?.id ?? null,
);
const organizationDetailsCard = ref<HTMLElement | null>(null);
const packageSection = ref<HTMLElement | null>(null);
const hasSelectedPackage = computed(() => selectedPackageId.value !== null);

const form = useForm({
    package_id: selectedPackageId.value,
    organization_name: props.partner?.organization_name ?? '',
    contact_person: props.partner?.contact_person ?? '',
    email: props.partner?.email ?? '',
    phone: props.partner?.phone ?? '',
    physical_address: props.partner?.physical_address ?? '',
});

watch(selectedPackageId, (val) => {
    form.package_id = val;
});

/**
 * Everything still standing between the user and a successful submit, in the
 * order they appear on the page. The submit button mirrors the server's
 * required fields, so the form cannot look ready and then be rejected.
 */
const submitBlockers = computed(() => {
    const blockers: string[] = [];

    if (!selectedPackageId.value) {
        blockers.push('Choose a partnership package above.');
    }

    if (!form.organization_name?.trim()) {
        blockers.push('Enter your organization name.');
    }

    if (!form.contact_person?.trim()) {
        blockers.push('Enter a contact person.');
    }

    if (!form.email?.trim()) {
        blockers.push('Enter a contact email address.');
    }

    return blockers;
});

function scrollToPackages() {
    const el = (packageSection.value as { $el?: HTMLElement } | HTMLElement | null);
    const node = (el as { $el?: HTMLElement })?.$el ?? (el as HTMLElement | null);

    node?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function selectPackage(pkg: SponsorshipPackage) {
    selectedPackageId.value = pkg.id;

    await nextTick();

    const cardEl = organizationDetailsCard.value?.$el ?? organizationDetailsCard.value;

    if (cardEl && typeof cardEl.scrollIntoView === 'function') {
        cardEl.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });
    }

    window.setTimeout(() => {
        document.getElementById('organization_name')?.focus();
    }, 250);
}

function saveDraft() {
    form.post('/partner/expression-of-interest/draft', {
        preserveScroll: true,
        onError: () => {
            toast.error(
                'Failed to save draft. Please check the form for errors.',
            );
        },
    });
}

function submit() {
    form.post('/partner/expression-of-interest', {
        onError: () => {
            toast.error(
                'Please fill in all required fields before submitting.',
            );
        },
    });
}
</script>

<template>
    <div class="space-y-8">
        <div class="flex items-start justify-between">
            <div>
                <h1 class="font-heading text-3xl font-bold tracking-tight">
                    Expression of Interest
                </h1>
                <p class="mt-1 text-muted-foreground">
                    Select a partnership package and provide your organization
                    details.
                </p>
            </div>
            <Link v-if="hasExistingPartner" href="/partner/dashboard">
                <Button variant="outline" size="sm">
                    <LayoutDashboard class="mr-2 h-4 w-4" />
                    Back to Dashboard
                </Button>
            </Link>
        </div>

        <!-- Status Banner -->
        <Alert v-if="isSubmitted" class="border-green-200 bg-green-50">
            <CheckCircle2 class="h-4 w-4 text-green-600" />
            <AlertTitle class="text-green-800"
                >Expression of Interest Submitted</AlertTitle
            >
            <AlertDescription class="text-green-700">
                Your expression of interest has been submitted and is under
                review by the AHAIC team. You can still edit your details below
                until the review is complete.
            </AlertDescription>
        </Alert>

        <Alert v-else-if="isRejected" class="border-red-200 bg-red-50">
            <Info class="h-4 w-4 text-red-600" />
            <AlertTitle class="text-red-800"
                >Updates Needed Before Approval</AlertTitle
            >
            <AlertDescription class="text-red-700">
                Your last expression of interest was reviewed and needs
                revisions before it can move to package confirmation. Update the
                details below and submit again when ready.
            </AlertDescription>
        </Alert>

        <Alert v-else-if="isDraft" class="border-amber-200 bg-amber-50">
            <FileEdit class="h-4 w-4 text-amber-600" />
            <AlertTitle class="text-amber-800">Draft Saved</AlertTitle>
            <AlertDescription class="text-amber-700">
                Your expression of interest is saved as a draft. Complete the
                form and click
                <strong>"Submit Expression of Interest"</strong> when you're
                ready.
            </AlertDescription>
        </Alert>

        <!-- Package Selection -->
        <div ref="packageSection">
            <h2 class="mb-4 font-heading text-xl font-semibold">
                Select a Package
                <Badge
                    v-if="selectedPackageId"
                    variant="outline"
                    class="ml-2 text-xs"
                >
                    {{
                        packages.find((p) => p.id === selectedPackageId)
                            ?.name ?? 'Selected'
                    }}
                </Badge>
            </h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <PackageCard
                    v-for="pkg in packages"
                    :key="pkg.id"
                    :package="pkg"
                    :selected="selectedPackageId === pkg.id"
                    @select="selectPackage(pkg)"
                />
            </div>
            <InputError
                v-if="form.errors.package_id"
                :message="form.errors.package_id"
                class="mt-2"
            />
        </div>

        <Separator />

        <!-- Organization Details Form -->
        <Card
            ref="organizationDetailsCard"
            :class="
                hasSelectedPackage
                    ? 'border-primary/40 shadow-sm ring-1 ring-primary/10'
                    : ''
            "
        >
            <CardHeader>
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle>Organization Details</CardTitle>
                        <CardDescription
                            >Provide information about your
                            organization.</CardDescription
                        >
                        <p
                            v-if="hasSelectedPackage"
                            class="mt-2 text-sm font-medium text-primary"
                        >
                            Package selected. Complete your organization
                            details below to continue.
                        </p>
                    </div>
                    <Badge
                        v-if="hasExistingPartner"
                        :variant="
                            isSubmitted
                                ? 'default'
                                : isRejected
                                  ? 'destructive'
                                  : 'secondary'
                        "
                    >
                        {{
                            isSubmitted
                                ? 'Submitted'
                                : isRejected
                                  ? 'Needs Review'
                                  : 'Draft'
                        }}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="organization_name"
                                >Organization Name
                                <span class="text-destructive">*</span></Label
                            >
                            <Input
                                id="organization_name"
                                v-model="form.organization_name"
                                placeholder="Enter organization name"
                            />
                            <InputError
                                :message="form.errors.organization_name"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="contact_person"
                                >Contact Person
                                <span class="text-destructive">*</span></Label
                            >
                            <Input
                                id="contact_person"
                                v-model="form.contact_person"
                                placeholder="Full name"
                            />
                            <InputError :message="form.errors.contact_person" />
                        </div>

                        <div class="space-y-2">
                            <Label for="email"
                                >Email Address
                                <span class="text-destructive">*</span></Label
                            >
                            <Input
                                id="email"
                                type="email"
                                v-model="form.email"
                                placeholder="email@example.com"
                            />
                            <InputError :message="form.errors.email" />
                        </div>

                        <div class="space-y-2">
                            <Label for="phone">Phone Number</Label>
                            <Input
                                id="phone"
                                v-model="form.phone"
                                placeholder="+254 700 000 000"
                            />
                            <InputError :message="form.errors.phone" />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="physical_address">Physical Address</Label>
                        <Input
                            id="physical_address"
                            v-model="form.physical_address"
                            placeholder="Street address, city, country"
                        />
                        <InputError :message="form.errors.physical_address" />
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex flex-col gap-3">
                <BlockedActionHint
                    :reasons="submitBlockers"
                    :action-label="!selectedPackageId ? 'Take me to the packages' : undefined"
                    class="w-full"
                    @resolve="scrollToPackages"
                />

                <div class="flex w-full flex-col gap-3 sm:flex-row sm:justify-between">
                <Button
                    variant="outline"
                    @click="saveDraft"
                    :disabled="form.processing"
                >
                    <Save class="mr-2 h-4 w-4" />
                    {{ hasExistingPartner ? 'Update Draft' : 'Save Draft' }}
                </Button>
                <Button
                    @click="submit"
                    :disabled="form.processing || submitBlockers.length > 0"
                >
                    <Send class="mr-2 h-4 w-4" />
                    {{
                        isSubmitted || isRejected
                            ? 'Update & Resubmit'
                            : 'Submit Expression of Interest'
                    }}
                    <ChevronRight class="ml-1 h-4 w-4" />
                </Button>
                </div>
            </CardFooter>
        </Card>

        <!-- Help Text -->
        <div
            class="flex items-start gap-3 rounded-lg border border-border/50 bg-muted/30 p-4 text-sm text-muted-foreground"
        >
            <Info class="mt-0.5 h-4 w-4 shrink-0" />
            <div>
                <p>
                    <strong>Save Draft</strong> saves your progress without
                    submitting. You can return to complete it anytime from your
                    <Link
                        href="/partner/dashboard"
                        class="text-primary underline underline-offset-2 hover:text-primary/80"
                        >Dashboard</Link
                    >.
                </p>
                <p class="mt-1">
                    <strong>Submit</strong> sends your expression of interest to
                    the AHAIC team for review. You'll be notified once it's been
                    processed.
                </p>
            </div>
        </div>
    </div>
</template>
