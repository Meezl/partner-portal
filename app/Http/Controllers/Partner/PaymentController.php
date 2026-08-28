<?php

namespace App\Http\Controllers\Partner;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentSubmittedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    /**
     * Show the payment form with pending invoices.
     */
    public function create(Request $request): Response
    {
        $partner = $request->user()->partner;

        $pendingInvoices = Invoice::where('partner_id', $partner->id)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
            ->get();

        return Inertia::render('Partner/Payment', [
            'partner' => $partner,
            'invoices' => $pendingInvoices,
            'paymentMethod' => 'bank_transfer',
        ]);
    }

    /**
     * Store a new payment record.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_id' => ['required', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:bank_transfer'],
            'transaction_reference' => ['required', 'string', 'max:255'],
            'supporting_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $partner = $request->user()->partner;

        // Verify invoice belongs to partner
        $invoice = Invoice::where('id', $validated['invoice_id'])
            ->where('partner_id', $partner->id)
            ->firstOrFail();

        $documentPath = $request->file('supporting_document')->store("payments/{$partner->id}", config('ahaic.disks.private'));

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'partner_id' => $partner->id,
            'amount' => $validated['amount'],
            'currency' => $invoice->currency,
            'payment_method' => 'bank_transfer',
            'transaction_reference' => $validated['transaction_reference'],
            'supporting_document_path' => $documentPath,
            'status' => PaymentStatus::Pending,
        ]);

        $financeUsers = User::where('role', UserRole::Finance->value)->get();
        if ($financeUsers->isNotEmpty()) {
            Notification::send(
                $financeUsers,
                new PaymentSubmittedNotification($payment)
            );
        }

        return redirect()->route('partner.dashboard')
            ->with('success', 'Your payment has been submitted and is being verified.');
    }
}
