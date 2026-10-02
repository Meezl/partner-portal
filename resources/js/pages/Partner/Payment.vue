<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Building2, CheckCircle2, CreditCard, Landmark, Send } from 'lucide-vue-next';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import FileUpload from '@/components/shared/FileUpload.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import PartnerLayout from '@/layouts/PartnerLayout.vue';
import { formatCalendarDate } from '@/lib/utils';
import type { Partner, Invoice } from '@/types/partner';

defineOptions({ layout: PartnerLayout });

interface PaymentTypeOption {
    value: string;
    label: string;
    document: string;
    reference: string;
}

const props = defineProps<{
    partner: Partner;
    invoices: Invoice[];
    paymentMethod: string;
    /** Pay now or pay later, with the wording each one uses. */
    paymentTypes: PaymentTypeOption[];
}>();

const pendingInvoices = computed(() =>
    props.invoices.filter((i) => i.status !== 'paid' && i.status !== 'cancelled'),
);

const form = useForm({
    invoice_id: pendingInvoices.value[0]?.id?.toString() ?? '',
    // Nothing is preselected: the partner picks a route before step 3 appears.
    payment_type: '',
    amount: pendingInvoices.value[0]?.amount?.toString() ?? '',
    payment_method: props.paymentMethod,
    transaction_reference: '',
    supporting_document: null as File | null,
});

const selectedInvoice = computed(
    () => pendingInvoices.value.find((i) => i.id === Number(form.invoice_id)) ?? null,
);

const payingNow = computed(() => form.payment_type === 'proof_of_payment');
const payingLater = computed(() => form.payment_type === 'purchase_order');
const hasChosen = computed(() => payingNow.value || payingLater.value);

/** The wording for whichever route the partner picked. */
const selectedType = computed(
    () => props.paymentTypes.find((type) => type.value === form.payment_type) ?? null,
);

/**
 * Only shown once "Pay now" is chosen — a partner settling later has no
 * transfer to make, so the bank details are noise on their path.
 */
const bankDetails = computed(() => {
    if (!payingNow.value) {
        return null;
    }

    const details = selectedInvoice.value?.bank_details ?? null;

    if (!details) {
        return null;
    }

    // A detail the conference has not configured — branch code, say — would
    // otherwise render as a labelled blank.
    const filled = Object.entries(details).filter(
        ([, value]) => String(value ?? '').trim() !== '',
    );

    return filled.length ? Object.fromEntries(filled) : null;
});

const optionBlurbs: Record<string, string> = {
    proof_of_payment: 'Transfer the funds by bank transfer and upload your payment confirmation.',
    purchase_order: 'Submit your LPO/PO now and complete payment before the conference.',
};

function onInvoiceChange(val: string | number | bigint | Record<string, any> | null) {
    if (typeof val !== 'string') {
        return;
    }

    form.invoice_id = val;
    const inv = pendingInvoices.value.find((i) => i.id === Number(val));

    if (inv) {
        form.amount = inv.amount.toString();
    }
}

function choose(type: string) {
    form.payment_type = type;
    // The two routes attach different documents, so a file picked for one is
    // never carried over to the other.
    form.supporting_document = null;
    form.transaction_reference = '';
    form.clearErrors();
}

function submit() {
    form.post('/partner/payment', { forceFormData: true });
}

function formatCurrency(amount: number, currency: string) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(amount);
}

function handleSupportingDocument(file: File | null) {
    form.supporting_document = file;
}
</script>

<template>
    <div class="space-y-8">
        <div>
            <h1 class="font-heading text-3xl font-bold tracking-tight">Settle Your Invoice</h1>
            <p class="mt-1 text-muted-foreground">
                Pay now and upload your proof of payment, or pay later by submitting your
                purchase order (PO).
            </p>
        </div>

        <div v-if="pendingInvoices.length === 0">
            <Card>
                <CardContent class="flex flex-col items-center gap-4 py-12">
                    <CreditCard class="h-12 w-12 text-muted-foreground" />
                    <p class="text-sm text-muted-foreground">
                        No pending invoices. All payments are up to date.
                    </p>
                </CardContent>
            </Card>
        </div>

        <div v-else class="space-y-6">
            <!-- 1. Invoice details -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <span class="font-mono text-sm text-muted-foreground">1</span>
                        Invoice Details
                    </CardTitle>
                    <CardDescription>Your outstanding invoice.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-5">
                    <!-- Only worth a picker when there is more than one to settle. -->
                    <div v-if="pendingInvoices.length > 1" class="space-y-2">
                        <Label for="invoice_id">Select invoice</Label>
                        <Select v-model="form.invoice_id" @update:model-value="onInvoiceChange">
                            <SelectTrigger>
                                <SelectValue placeholder="Select an invoice" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="inv in pendingInvoices"
                                    :key="inv.id"
                                    :value="inv.id.toString()"
                                >
                                    {{ inv.invoice_number }} -
                                    {{ formatCurrency(inv.amount, inv.currency) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.invoice_id" />
                    </div>

                    <div v-if="selectedInvoice" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <p class="text-xs text-muted-foreground">Invoice</p>
                            <p class="font-medium">{{ selectedInvoice.invoice_number }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Amount due</p>
                            <p class="font-heading text-lg">
                                {{ formatCurrency(selectedInvoice.amount, selectedInvoice.currency) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Due date</p>
                            <p class="font-medium">
                                {{ formatCalendarDate(selectedInvoice.due_date, { day: 'numeric', month: 'long', year: 'numeric' }) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Status</p>
                            <StatusBadge :status="selectedInvoice.status" type="invoice" />
                        </div>
                    </div>
                    <InputError :message="form.errors.amount" />
                </CardContent>
            </Card>

            <!-- 2. Choose a payment option -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <span class="font-mono text-sm text-muted-foreground">2</span>
                        Choose Your Payment Option
                    </CardTitle>
                    <CardDescription>How would you like to proceed?</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label
                            v-for="(type, index) in paymentTypes"
                            :key="type.value"
                            class="flex cursor-pointer flex-col gap-1 rounded-lg border p-4 transition"
                            :class="
                                form.payment_type === type.value
                                    ? 'border-primary bg-primary/5'
                                    : 'border-input hover:border-primary/50'
                            "
                        >
                            <input
                                type="radio"
                                class="sr-only"
                                :value="type.value"
                                :checked="form.payment_type === type.value"
                                @change="choose(type.value)"
                            />
                            <span class="text-xs text-muted-foreground">Option {{ index + 1 }}</span>
                            <span class="font-medium">
                                {{ type.value === 'purchase_order' ? 'Pay Later' : 'Pay Now' }}
                            </span>
                            <span class="text-sm text-muted-foreground">
                                {{ optionBlurbs[type.value] }}
                            </span>
                        </label>
                    </div>
                    <InputError :message="form.errors.payment_type" class="mt-2" />
                </CardContent>
            </Card>

            <!-- 3. Complete the required action -->
            <Card v-if="!hasChosen" class="border-dashed">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-muted-foreground">
                        <span class="font-mono text-sm">3</span>
                        Complete the Required Action
                    </CardTitle>
                    <CardDescription>
                        Choose an option above and the step you need will appear here.
                    </CardDescription>
                </CardHeader>
            </Card>

            <Card v-else>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <span class="font-mono text-sm text-muted-foreground">3</span>
                        {{ payingLater ? 'Submit LPO/PO' : 'Bank Transfer' }}
                    </CardTitle>
                    <CardDescription>
                        {{
                            payingLater
                                ? 'Please upload your Local Purchase Order (LPO) or Purchase Order (PO).'
                                : 'Payment is accepted via bank transfer only.'
                        }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Bank details, for the transfer route only -->
                        <div v-if="payingNow" class="rounded-lg border bg-muted/30 p-4">
                            <p class="flex items-center gap-2 text-sm font-medium">
                                <Building2 class="h-4 w-4" />
                                Bank Details
                            </p>
                            <div v-if="bankDetails" class="mt-3 grid gap-3 sm:grid-cols-2">
                                <div v-for="(value, key) in bankDetails" :key="key">
                                    <p class="text-xs text-muted-foreground capitalize">
                                        {{ String(key).replace(/_/g, ' ') }}
                                    </p>
                                    <p class="text-sm font-medium">{{ value }}</p>
                                </div>
                            </div>
                            <p v-else class="mt-2 text-sm text-muted-foreground">
                                Bank details will appear once your invoice is issued. Contact the
                                finance team if you need them sooner.
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="transaction_reference">{{ selectedType?.reference }}</Label>
                            <Input
                                id="transaction_reference"
                                v-model="form.transaction_reference"
                                :placeholder="
                                    payingLater
                                        ? 'Enter your LPO or PO number'
                                        : 'Enter transaction or receipt reference'
                                "
                            />
                            <InputError :message="form.errors.transaction_reference" />
                        </div>

                        <div class="space-y-2">
                            <Label>
                                {{ payingLater ? 'Upload your LPO/PO' : 'Upload Proof of Payment' }}
                            </Label>
                            <!-- Pay Later already says this in the card description. -->
                            <p v-if="payingNow" class="text-sm text-muted-foreground">
                                Please upload your bank slip, transfer confirmation, or other
                                supporting payment document.
                            </p>
                            <FileUpload
                                accept=".pdf,.jpg,.jpeg,.png"
                                :max-size="10"
                                :instructions="[
                                    'Drag and drop your file here or click to browse.',
                                    'Maximum file size: 10 MB per file',
                                    'Accepted formats: PDF, JPG, JPEG, PNG',
                                ]"
                                @change="handleSupportingDocument"
                            />
                            <InputError :message="form.errors.supporting_document" />
                        </div>

                        <p
                            v-if="payingLater"
                            class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
                        >
                            All payments must be completed before the conference.
                        </p>
                    </form>
                </CardContent>
                <CardFooter class="flex justify-end">
                    <Button @click="submit" :disabled="form.processing">
                        <Send class="mr-2 h-4 w-4" />
                        {{ payingLater ? 'Submit LPO/PO' : 'Submit Payment Proof' }}
                    </Button>
                </CardFooter>
            </Card>

            <!-- 4. What happens next -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <span class="font-mono text-sm text-muted-foreground">4</span>
                        Finance Review
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="flex items-start gap-2 text-sm text-muted-foreground">
                        <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>
                            Your payment documentation will be reviewed by the AHAIC Finance Team.
                            You will then receive confirmation once your payment is finalized.
                        </span>
                    </p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
