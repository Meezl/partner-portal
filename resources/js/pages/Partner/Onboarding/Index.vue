<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    Building2,
    CalendarDays,
    Megaphone,
    Users,
    Pencil,
    CheckCircle2,
    Send,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import OnboardingProgress from '@/components/partner/OnboardingProgress.vue';
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import PartnerLayout from '@/layouts/PartnerLayout.vue';
import { canAdvanceToReview, getOnboardingSections } from '@/lib/onboarding-workflow.js';
import type { Partner, OnboardingProgress as OnboardingProgressType } from '@/types/partner';

defineOptions({ layout: PartnerLayout });

const props = defineProps<{
    partner: Partner;
    progress: OnboardingProgressType;
}>();

const showReviewDialog = ref(false);

const allComplete = computed(() => canAdvanceToReview(props.progress));

const iconMap = {
    organization: Building2,
    sessions: CalendarDays,
    communications: Megaphone,
    contacts: Users,
};

const sections = computed(() =>
    getOnboardingSections(props.progress).map((section) => ({
        ...section,
        icon: iconMap[section.key as keyof typeof iconMap],
    })),
);

function submitAll() {
    showReviewDialog.value = false;
    router.visit('/partner/review');
}
</script>

<template>
    <div class="space-y-8">
        <div>
            <h1 class="font-heading text-3xl font-bold tracking-tight">Onboarding</h1>
            <p class="text-muted-foreground mt-1">Complete all sections below to finalize your partnership details.</p>
        </div>

        <OnboardingProgress :progress="progress" />

        <div class="grid gap-4 sm:grid-cols-2">
            <Card v-for="section in sections" :key="section.title">
                <CardHeader>
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-lg"
                                :class="section.progress >= 100 ? 'bg-green-100 text-green-700' : 'bg-muted text-muted-foreground'"
                            >
                                <component :is="section.icon" class="h-5 w-5" />
                            </div>
                            <div>
                                <CardTitle class="text-base">{{ section.title }}</CardTitle>
                                <CardDescription>{{ section.description }}</CardDescription>
                            </div>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center gap-3">
                        <div class="bg-secondary h-2 flex-1 rounded-full">
                            <div
                                class="h-2 rounded-full transition-all"
                                :class="section.progress >= 100 ? 'bg-green-500' : 'bg-primary'"
                                :style="{ width: section.progress + '%' }"
                            />
                        </div>
                        <span class="text-muted-foreground text-sm font-medium">{{ section.progress }}%</span>
                    </div>
                </CardContent>
                <CardFooter>
                    <Link :href="section.href" class="w-full">
                        <Button variant="outline" class="w-full">
                            <component :is="section.progress >= 100 ? CheckCircle2 : Pencil" class="mr-2 h-4 w-4" />
                            {{ section.progress >= 100 ? 'Review' : 'Edit' }}
                        </Button>
                    </Link>
                </CardFooter>
            </Card>
        </div>

        <Separator />

        <Card>
            <CardContent class="flex items-center justify-between py-6">
                <div>
                    <h3 class="font-semibold">Ready to Submit?</h3>
                    <p class="text-muted-foreground text-sm">
                        {{ allComplete ? 'All sections are complete. You can now submit your onboarding details.' : 'Complete all sections to enable submission.' }}
                    </p>
                </div>
                <Button :disabled="!allComplete" size="lg" @click="showReviewDialog = true">
                    <Send class="mr-2 h-4 w-4" />
                    Review Submission
                </Button>
            </CardContent>
        </Card>

        <ConfirmDialog
            :open="showReviewDialog"
            title="Continue to Review"
            description="All onboarding sections are complete. Continue to the final review page before submitting your partnership materials."
            confirm-label="Go to Review"
            @confirm="submitAll"
            @cancel="showReviewDialog = false"
        />
    </div>
</template>
