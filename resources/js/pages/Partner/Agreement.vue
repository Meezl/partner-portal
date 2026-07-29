<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    Download,
    Upload,
    FileCheck2,
    Clock,
    CheckCircle2,
    ShieldCheck,
    PenTool,
    FileText,
} from 'lucide-vue-next';
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
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PartnerLayout from '@/layouts/PartnerLayout.vue';
import { getAgreementStepState } from '@/lib/agreement-workflow.js';
import type { Partner, Agreement, Invoice } from '@/types/partner';

defineOptions({ layout: PartnerLayout });

const props = defineProps<{
    partner: Partner;
    agreement: Agreement;
    invoice: Invoice | null;
}>();

const uploadForm = useForm({
    signed_document: null as File | null,
});

const signForm = useForm({
    signer_name:
        props.agreement.signed_by_name ?? props.partner.contact_person ?? '',
    accept_terms: false,
});

function downloadAgreement() {
    window.open('/partner/agreement/download', '_blank');
}

function downloadInvoice() {
    if (!props.invoice) {
        return;
    }

    window.open(`/partner/invoices/${props.invoice.id}/download`, '_blank');
}

function handleFileSelect(file: File) {
    uploadForm.signed_document = file;
}

function digitallySign() {
    signForm.post('/partner/agreement/sign');
}

function uploadSigned() {
    uploadForm.post('/partner/agreement/upload', {
        forceFormData: true,
    });
}

const statusSteps = [
    { key: 'pending', label: 'Pending', icon: Clock },
    { key: 'signed', label: 'Signed', icon: FileCheck2 },
    { key: 'verified', label: 'Verified', icon: ShieldCheck },
];

function getStepState(stepKey: string) {
    return getAgreementStepState(props.agreement.status, stepKey);
}
</script>

<template>
    <div class="space-y-8">
        <div>
            <h1 class="font-heading text-3xl font-bold tracking-tight">
                Partnership Agreement
            </h1>
            <p class="mt-1 text-muted-foreground">
                Review your agreement, then digitally sign it or upload a signed
                PDF.
            </p>
        </div>

        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CardTitle>Agreement Status</CardTitle>
                    <StatusBadge :status="agreement.status" />
                </div>
            </CardHeader>
            <CardContent>
                <div class="flex items-center justify-between gap-4">
                    <div
                        v-for="(step, i) in statusSteps"
                        :key="step.key"
                        class="flex flex-1 items-center"
                    >
                        <div class="flex flex-col items-center gap-2">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-full"
                                :class="{
                                    'bg-primary text-primary-foreground':
                                        getStepState(step.key) === 'current',
                                    'bg-green-100 text-green-700':
                                        getStepState(step.key) === 'completed',
                                    'bg-muted text-muted-foreground':
                                        getStepState(step.key) === 'upcoming',
                                }"
                            >
                                <component :is="step.icon" class="h-5 w-5" />
                            </div>
                            <span class="text-xs font-medium">{{
                                step.label
                            }}</span>
                        </div>
                        <div
                            v-if="i < statusSteps.length - 1"
                            class="mx-2 h-px flex-1 bg-border"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Download Agreement</CardTitle>
                    <CardDescription
                        >Download the partnership agreement document for review
                        and signing.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <div v-if="agreement.document_path" class="space-y-4">
                        <div
                            class="flex items-center gap-3 rounded-lg bg-muted p-4"
                        >
                            <FileCheck2 class="h-8 w-8 text-blue-500" />
                            <div>
                                <p class="text-sm font-medium">
                                    Partnership Agreement
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Generated
                                    {{
                                        agreement.generated_at
                                            ? new Date(
                                                  agreement.generated_at,
                                              ).toLocaleDateString()
                                            : 'N/A'
                                    }}
                                </p>
                            </div>
                        </div>
                        <Button
                            variant="outline"
                            class="w-full"
                            @click="downloadAgreement"
                        >
                            <Download class="mr-2 h-4 w-4" />
                            Download Agreement
                        </Button>
                    </div>
                    <div
                        v-else
                        class="py-8 text-center text-sm text-muted-foreground"
                    >
                        Agreement document has not been generated yet.
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Digital Signature</CardTitle>
                    <CardDescription
                        >Type the authorized signatory name to sign instantly in
                        the portal.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <div
                        v-if="agreement.status === 'verified'"
                        class="flex flex-col items-center gap-3 py-8"
                    >
                        <CheckCircle2 class="h-12 w-12 text-green-500" />
                        <p class="font-medium text-green-700">
                            Agreement verified
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Signed on
                            {{
                                agreement.signed_at
                                    ? new Date(
                                          agreement.signed_at,
                                      ).toLocaleDateString()
                                    : 'N/A'
                            }}
                        </p>
                    </div>
                    <div
                        v-else-if="agreement.signed_document_path"
                        class="flex flex-col items-center gap-3 py-8"
                    >
                        <Clock class="h-12 w-12 text-muted-foreground" />
                        <p class="font-medium">Signed agreement received</p>
                        <p class="text-sm text-muted-foreground">
                            Your agreement is complete and the invoice has been
                            prepared.
                        </p>
                    </div>
                    <div v-else class="space-y-4">
                        <div class="space-y-2">
                            <Label for="signer_name"
                                >Authorized Signatory Name</Label
                            >
                            <Input
                                id="signer_name"
                                v-model="signForm.signer_name"
                                placeholder="Full legal name"
                            />
                            <InputError
                                :message="signForm.errors.signer_name"
                            />
                        </div>
                        <label
                            class="flex items-start gap-3 rounded-lg border p-3 text-sm"
                        >
                            <input
                                v-model="signForm.accept_terms"
                                type="checkbox"
                                class="mt-1"
                            />
                            <span
                                >I confirm that I am authorized to sign on
                                behalf of this organization.</span
                            >
                        </label>
                        <InputError :message="signForm.errors.accept_terms" />
                        <Button
                            class="w-full"
                            :disabled="
                                signForm.processing ||
                                !signForm.signer_name ||
                                !signForm.accept_terms
                            "
                            @click="digitallySign"
                        >
                            <PenTool class="mr-2 h-4 w-4" />
                            Digitally Sign Agreement
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Upload Signed Agreement</CardTitle>
                    <CardDescription
                        >Prefer wet signature? Upload the signed agreement
                        document to proceed.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <div
                        v-if="
                            agreement.signed_method === 'upload' &&
                            agreement.signed_document_path
                        "
                        class="flex flex-col items-center gap-3 py-8"
                    >
                        <CheckCircle2 class="h-12 w-12 text-green-500" />
                        <p class="font-medium">
                            Uploaded signed agreement received
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Received
                            {{
                                agreement.signed_at
                                    ? new Date(
                                          agreement.signed_at,
                                      ).toLocaleDateString()
                                    : 'N/A'
                            }}
                        </p>
                    </div>
                    <div
                        v-else-if="agreement.signed_method === 'digital'"
                        class="flex flex-col items-center gap-3 py-8"
                    >
                        <CheckCircle2 class="h-12 w-12 text-green-500" />
                        <p class="font-medium">Agreement digitally signed</p>
                        <p class="text-sm text-muted-foreground">
                            You can download the signed agreement above.
                        </p>
                    </div>
                    <div v-else class="space-y-4">
                        <FileUpload
                            accept=".pdf,.doc,.docx"
                            @select="handleFileSelect"
                        />
                        <Button
                            class="w-full"
                            :disabled="
                                !uploadForm.signed_document ||
                                uploadForm.processing
                            "
                            @click="uploadSigned"
                        >
                            <Upload class="mr-2 h-4 w-4" />
                            Upload Signed Agreement
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="invoice" class="border-primary/20 bg-primary/5">
                <CardHeader>
                    <CardTitle>Invoice Ready</CardTitle>
                    <CardDescription
                        >Your agreement is complete and the invoice has been
                        generated for payment.</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-4">
                    <div>
                        <p class="font-medium">{{ invoice.invoice_number }}</p>
                        <p class="text-sm text-muted-foreground">
                            Due
                            {{
                                new Date(invoice.due_date).toLocaleDateString()
                            }}
                            •
                            {{ invoice.currency }}
                            {{ Number(invoice.amount).toLocaleString() }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <Button
                            variant="outline"
                            class="flex-1"
                            @click="downloadInvoice"
                        >
                            <FileText class="mr-2 h-4 w-4" />
                            Download Invoice
                        </Button>
                        <Button as-child class="flex-1">
                            <a href="/partner/payment">Proceed to Payment</a>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
