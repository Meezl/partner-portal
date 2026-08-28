<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Partner;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoiceGeneratorService
{
    public function generate(Partner $partner): Invoice
    {
        $partner->loadMissing(['packages', 'conference']);
        $package = $partner->packages->first();
        $invoiceNumber = 'AHAIC'.date('Y').'INV'.str_pad((string) (Invoice::count() + 1), 2, '0', STR_PAD_LEFT);
        $customerCode = $partner->customer_code ?: $this->generateCustomerCode($partner);
        $bankDetails = config('ahaic.bank_details', []);
        $conference = $partner->conference;

        $invoice = Invoice::create([
            'partner_id' => $partner->id,
            'invoice_number' => $invoiceNumber,
            'customer_code' => $customerCode,
            'document_path' => null,
            'date_of_service' => now(),
            'due_date' => now()->addDays((int) config('ahaic.invoice_due_days', 30)),
            'amount' => $package?->price ?? 0,
            'currency' => $package?->currency ?? 'USD',
            'benefits_summary' => $package?->benefits,
            'bank_details' => $bankDetails,
            'additional_options' => [
                'package_name' => $package?->name,
                'package_tier' => $package?->tier?->value,
                'session_slots' => $package?->session_slots,
                'exhibition_space' => $package?->exhibition_space,
                'billing_address' => $partner->billing_address,
                'tax_details' => $partner->tax_details,
                'event_name' => $conference?->name ?? 'AHAIC',
                'event_dates' => $conference
                    ? $conference->start_date?->format('F j').' - '.$conference->end_date?->format('j, Y')
                    : null,
                'conference_year' => $conference?->year,
            ],
            'status' => InvoiceStatus::Sent,
            'sent_at' => now(),
            'notes' => 'Please quote invoice number '.$invoiceNumber.' when remitting payment. For billing support contact '.config('ahaic.finance_email', 'finance@ahaic.org').'.',
        ]);

        $pdf = Pdf::loadView('pdf.invoice', [
            'partner' => $partner,
            'invoice' => $invoice,
        ]);

        $filename = 'invoices/invoice_'.$invoice->id.'_'.time().'.pdf';
        Storage::disk(config('ahaic.disks.private'))->put($filename, $pdf->output());

        $invoice->update(['document_path' => $filename]);

        return $invoice;
    }

    private function generateCustomerCode(Partner $partner): string
    {
        $customerCode = 'AHAIC-CUST-'.str_pad((string) $partner->id, 5, '0', STR_PAD_LEFT);

        $partner->update(['customer_code' => $customerCode]);

        return $customerCode;
    }
}
