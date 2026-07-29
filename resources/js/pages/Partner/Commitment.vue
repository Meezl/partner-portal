<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { FileSignature, CheckCircle2 } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
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
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import PartnerLayout from '@/layouts/PartnerLayout.vue';
import type { Partner, SponsorshipPackage } from '@/types/partner';

defineOptions({ layout: PartnerLayout });

const props = defineProps<{
    partner: Partner;
    selectedPackage: SponsorshipPackage;
}>();

const form = useForm({
    billing_address: props.partner.billing_address ?? '',
    tax_details: props.partner.tax_details ?? '',
});

function submit() {
    form.put('/partner/commitment');
}

function formatCurrency(amount: number, currency: string) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency,
    }).format(amount);
}
</script>

<template>
    <div class="space-y-8">
        <div>
            <h1 class="font-heading text-3xl font-bold tracking-tight">
                Confirm Commitment
            </h1>
            <p class="mt-1 text-muted-foreground">
                Review your selected package and provide billing details to
                generate your agreement.
            </p>
        </div>

        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle>Selected Package</CardTitle>
                        <CardDescription>{{
                            selectedPackage.name
                        }}</CardDescription>
                    </div>
                    <Badge variant="secondary" class="capitalize">{{
                        selectedPackage.tier
                    }}</Badge>
                </div>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="space-y-1">
                        <p class="text-sm text-muted-foreground">Price</p>
                        <p class="text-lg font-semibold">
                            {{
                                formatCurrency(
                                    selectedPackage.price,
                                    selectedPackage.currency,
                                )
                            }}
                        </p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-sm text-muted-foreground">
                            Session Slots
                        </p>
                        <p class="text-lg font-semibold">
                            {{ selectedPackage.session_slots }}
                        </p>
                    </div>
                    <div
                        v-if="selectedPackage.exhibition_space"
                        class="space-y-1"
                    >
                        <p class="text-sm text-muted-foreground">
                            Exhibition Space
                        </p>
                        <p class="text-lg font-semibold">
                            {{ selectedPackage.exhibition_space }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="
                        selectedPackage.benefits &&
                        selectedPackage.benefits.length > 0
                    "
                >
                    <p class="mb-2 text-sm font-medium text-muted-foreground">
                        Benefits
                    </p>
                    <ul class="space-y-1">
                        <li
                            v-for="(benefit, i) in selectedPackage.benefits"
                            :key="i"
                            class="flex items-center gap-2 text-sm"
                        >
                            <CheckCircle2 class="h-4 w-4 text-green-500" />
                            {{ benefit }}
                        </li>
                    </ul>
                </div>
            </CardContent>
        </Card>

        <Separator />

        <Card>
            <CardHeader>
                <CardTitle>Billing Information</CardTitle>
                <CardDescription
                    >Provide your billing address and tax details so we can
                    generate your agreement.</CardDescription
                >
            </CardHeader>
            <CardContent>
                <form
                    id="commitment-form"
                    @submit.prevent="submit"
                    class="space-y-6"
                >
                    <div class="space-y-2">
                        <Label for="billing_address">Billing Address</Label>
                        <textarea
                            id="billing_address"
                            v-model="form.billing_address"
                            rows="3"
                            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                            placeholder="Enter your full billing address"
                        />
                        <InputError :message="form.errors.billing_address" />
                    </div>

                    <div class="space-y-2">
                        <Label for="tax_details">Tax Details</Label>
                        <textarea
                            id="tax_details"
                            v-model="form.tax_details"
                            rows="3"
                            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                            placeholder="Tax ID / VAT number and other tax information"
                        />
                        <InputError :message="form.errors.tax_details" />
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-end">
                <Button
                    type="button"
                    :disabled="form.processing"
                    @click="submit"
                >
                    <FileSignature class="mr-2 h-4 w-4" />
                    Confirm &amp; Generate Agreement
                </Button>
            </CardFooter>
        </Card>
    </div>
</template>
