<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    /**
     * List all invoices for the partner.
     */
    public function index(Request $request): Response
    {
        $partner = $request->user()->partner;

        $invoices = Invoice::where('partner_id', $partner->id)
            ->latest()
            ->get();

        return Inertia::render('Partner/Invoices', [
            'partner' => $partner,
            'invoices' => $invoices,
        ]);
    }

    /**
     * Show a specific invoice.
     */
    public function show(Request $request, Invoice $invoice): Response
    {
        $partner = $request->user()->partner;

        if ($invoice->partner_id !== $partner->id) {
            abort(403, 'This invoice does not belong to your organization.');
        }

        $invoice->load('payments');

        return Inertia::render('Partner/InvoiceDetail', [
            'invoice' => $invoice,
        ]);
    }

    /**
     * Download an invoice PDF.
     */
    public function download(Request $request, Invoice $invoice): StreamedResponse
    {
        $partner = $request->user()->partner;

        if ($invoice->partner_id !== $partner->id) {
            abort(403, 'This invoice does not belong to your organization.');
        }

        if (! $invoice->document_path) {
            abort(404, 'Invoice document not found.');
        }

        return Storage::disk(config('ahaic.disks.private'))->download($invoice->document_path);
    }
}
