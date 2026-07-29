<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Download, CreditCard, FileText } from 'lucide-vue-next';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
} from '@/components/ui/card';
import PartnerLayout from '@/layouts/PartnerLayout.vue';
import type { Partner, Invoice } from '@/types/partner';

defineOptions({ layout: PartnerLayout });

const props = defineProps<{
    partner: Partner;
    invoices: Invoice[];
}>();

function formatCurrency(amount: number, currency: string) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency,
    }).format(amount);
}

function formatDate(date: string) {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function downloadInvoice(invoice: Invoice) {
    window.open(`/partner/invoices/${invoice.id}/download`, '_blank');
}

const totalOutstanding = props.invoices
    .filter((i) => i.status !== 'paid' && i.status !== 'cancelled')
    .reduce((sum, i) => sum + i.amount, 0);

const currency = props.invoices[0]?.currency ?? 'USD';
</script>

<template>
    <div class="space-y-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="font-heading text-3xl font-bold tracking-tight">
                    Invoices
                </h1>
                <p class="mt-1 text-muted-foreground">
                    View and manage your partnership invoices.
                </p>
            </div>
            <div v-if="totalOutstanding > 0" class="text-right">
                <p class="text-sm text-muted-foreground">Total Outstanding</p>
                <p class="text-xl font-bold">
                    {{ formatCurrency(totalOutstanding, currency) }}
                </p>
            </div>
        </div>

        <Card v-if="invoices.length === 0">
            <CardContent class="flex flex-col items-center gap-4 py-12">
                <FileText class="h-12 w-12 text-muted-foreground" />
                <p class="text-sm text-muted-foreground">No invoices yet.</p>
            </CardContent>
        </Card>

        <Card v-else>
            <CardHeader>
                <CardTitle>All Invoices</CardTitle>
                <CardDescription
                    >{{ invoices.length }} invoice(s) on record</CardDescription
                >
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Invoice #
                                </th>
                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Date
                                </th>
                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Due Date
                                </th>
                                <th
                                    class="px-4 py-3 text-right font-medium text-muted-foreground"
                                >
                                    Amount
                                </th>
                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Status
                                </th>
                                <th
                                    class="px-4 py-3 text-right font-medium text-muted-foreground"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="invoice in invoices"
                                :key="invoice.id"
                                class="border-b transition-colors hover:bg-muted/50"
                            >
                                <td class="px-4 py-3 font-medium">
                                    {{ invoice.invoice_number }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ formatDate(invoice.date_of_service) }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ formatDate(invoice.due_date) }}
                                </td>
                                <td class="px-4 py-3 text-right font-medium">
                                    {{
                                        formatCurrency(
                                            invoice.amount,
                                            invoice.currency,
                                        )
                                    }}
                                </td>
                                <td class="px-4 py-3">
                                    <StatusBadge :status="invoice.status" />
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div
                                        class="flex items-center justify-end gap-2"
                                    >
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="downloadInvoice(invoice)"
                                        >
                                            <Download class="h-4 w-4" />
                                        </Button>
                                        <Link
                                            v-if="
                                                invoice.status !== 'paid' &&
                                                invoice.status !== 'cancelled'
                                            "
                                            :href="`/partner/payment?invoice=${invoice.id}`"
                                        >
                                            <Button variant="outline" size="sm">
                                                <CreditCard
                                                    class="mr-1 h-4 w-4"
                                                />
                                                Pay
                                            </Button>
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
