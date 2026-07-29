<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { CreditCard, Building2, Landmark, Send } from 'lucide-vue-next';
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
import type { Partner, Invoice } from '@/types/partner';

defineOptions({ layout: PartnerLayout });

const props = defineProps<{
    partner: Partner;
    invoices: Invoice[];
    paymentMethod: string;
}>();

const pendingInvoices = computed(() =>
    props.invoices.filter(
        (i) => i.status !== 'paid' && i.status !== 'cancelled',
    ),
);

const selectedInvoice = computed(
    () =>
        pendingInvoices.value.find((i) => i.id === Number(form.invoice_id)) ??
        null,
);

const form = useForm({
    invoice_id: pendingInvoices.value[0]?.id?.toString() ?? '',
    amount: pendingInvoices.value[0]?.amount?.toString() ?? '',
    payment_method: props.paymentMethod,
    transaction_reference: '',
    supporting_document: null as File | null,
});

function onInvoiceChange(
    val: string | number | bigint | Record<string, any> | null,
) {
    if (typeof val !== 'string') {
        return;
    }

    form.invoice_id = val;
    const inv = pendingInvoices.value.find((i) => i.id === Number(val));

    if (inv) {
        form.amount = inv.amount.toString();
    }
}

function submit() {
    form.post('/partner/payment', {
        forceFormData: true,
    });
}

function formatCurrency(amount: number, currency: string) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency,
    }).format(amount);
}

const bankDetails = computed(() => selectedInvoice.value?.bank_details ?? null);

function handleSupportingDocument(file: File | null) {
    form.supporting_document = file;
}
</script>

<template>
    <div class="space-y-8">
        <div>
            <h1 class="font-heading text-3xl font-bold tracking-tight">
                Make Payment
            </h1>
            <p class="mt-1 text-muted-foreground">
                Pay by bank transfer and upload your supporting proof of payment.
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

        <div v-else class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Pending Invoices Summary</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div
                                v-for="inv in pendingInvoices"
                                :key="inv.id"
                                class="flex items-center justify-between rounded-lg border p-3"
                            >
                                <div>
                                    <p class="text-sm font-medium">
                                        {{ inv.invoice_number }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        Due
                                        {{
                                            new Date(
                                                inv.due_date,
                                            ).toLocaleDateString()
                                        }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <StatusBadge :status="inv.status" />
                                    <span class="font-semibold">{{
                                        formatCurrency(inv.amount, inv.currency)
                                    }}</span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Payment Details</CardTitle>
                        <CardDescription
                            >Select an invoice, complete the bank transfer, and upload the payment proof.</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submit" class="space-y-6">
                            <div class="space-y-2">
                                <Label for="invoice_id">Select Invoice</Label>
                                <Select
                                    v-model="form.invoice_id"
                                    @update:model-value="onInvoiceChange"
                                >
                                    <SelectTrigger>
                                        <SelectValue
                                            placeholder="Select an invoice"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="inv in pendingInvoices"
                                            :key="inv.id"
                                            :value="inv.id.toString()"
                                        >
                                            {{ inv.invoice_number }} -
                                            {{
                                                formatCurrency(
                                                    inv.amount,
                                                    inv.currency,
                                                )
                                            }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.invoice_id" />
                            </div>

                            <div class="space-y-2">
                                <Label for="amount">Amount</Label>
                                <Input
                                    id="amount"
                                    v-model="form.amount"
                                    type="number"
                                    step="0.01"
                                    readonly
                                />
                                <InputError :message="form.errors.amount" />
                            </div>

                            <div class="space-y-2">
                                <Label>Payment Method</Label>
                                <div
                                    class="flex items-center gap-3 rounded-lg border bg-muted/30 px-4 py-3"
                                >
                                    <Landmark class="h-5 w-5 text-primary" />
                                    <div>
                                        <p class="text-sm font-medium">
                                            Bank Transfer
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            Payment is accepted via bank transfer only.
                                        </p>
                                    </div>
                                </div>
                                <InputError
                                    :message="form.errors.payment_method"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="transaction_reference"
                                    >Transaction Reference</Label
                                >
                                <Input
                                    id="transaction_reference"
                                    v-model="form.transaction_reference"
                                    placeholder="Enter transaction or receipt reference"
                                />
                                <InputError
                                    :message="form.errors.transaction_reference"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label>Supporting Document</Label>
                                <FileUpload
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    :max-size="10"
                                    @change="handleSupportingDocument"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Upload a bank slip, transfer confirmation, or any supporting bank payment document.
                                </p>
                                <InputError
                                    :message="form.errors.supporting_document"
                                />
                            </div>
                        </form>
                    </CardContent>
                    <CardFooter class="flex justify-end">
                        <Button @click="submit" :disabled="form.processing">
                            <Send class="mr-2 h-4 w-4" />
                            Submit Payment Proof
                        </Button>
                    </CardFooter>
                </Card>
            </div>

            <div>
                <Card v-if="bankDetails" class="sticky top-4">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Building2 class="h-5 w-5" />
                            Bank Details
                        </CardTitle>
                        <CardDescription
                            >Use these details for your bank transfer, then upload the payment proof here.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            v-for="(value, key) in bankDetails"
                            :key="key"
                            class="space-y-1"
                        >
                            <p class="text-xs text-muted-foreground capitalize">
                                {{ String(key).replace(/_/g, ' ') }}
                            </p>
                            <p class="text-sm font-medium">{{ value }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card v-else class="sticky top-4">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Building2 class="h-5 w-5" />
                            Bank Details
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm text-muted-foreground">
                            Bank details will appear once you select an invoice.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
