<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\PartnerStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Notifications\PaymentConfirmedNotification;
use App\Notifications\PaymentRejectedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    /**
     * List all payments with filters.
     */
    public function index(Request $request): Response
    {
        $query = Payment::with(['partner', 'invoice']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('partner')) {
            $query->where('partner_id', $request->input('partner'));
        }

        $payments = $query->latest()->paginate(20)->withQueryString();
        $paymentCollection = $payments->getCollection();

        return Inertia::render('Admin/Finance/Payments', [
            'payments' => $paymentCollection->values()->all(),
            'stats' => [
                'pendingCount' => $paymentCollection
                    ->filter(fn (Payment $payment) => $payment->status === PaymentStatus::Pending)
                    ->count(),
                'confirmedTotal' => (float) $paymentCollection
                    ->filter(fn (Payment $payment) => $payment->status === PaymentStatus::Confirmed)
                    ->sum('amount'),
                'pendingTotal' => (float) $paymentCollection
                    ->filter(fn (Payment $payment) => $payment->status === PaymentStatus::Pending)
                    ->sum('amount'),
            ],
            'filters' => $request->only(['status', 'partner']),
            'statuses' => PaymentStatus::cases(),
        ]);
    }

    public function downloadProof(Payment $payment): StreamedResponse
    {
        $disk = Storage::disk(config('ahaic.disks.private'));

        if (! $payment->supporting_document_path || ! $disk->exists($payment->supporting_document_path)) {
            abort(404, 'Supporting document not found.');
        }

        return $disk->download($payment->supporting_document_path);
    }

    /**
     * Confirm a payment.
     */
    public function confirm(Request $request, Payment $payment): RedirectResponse
    {
        $payment->loadMissing(['partner.user', 'invoice']);

        $payment->update([
            'status' => PaymentStatus::Confirmed,
            'confirmed_by' => $request->user()->id,
            'confirmed_at' => now(),
        ]);

        $payment->invoice?->update([
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
        ]);

        // Update partner status to confirmed
        $partner = $payment->partner;
        $partner->update([
            'status' => PartnerStatus::Confirmed,
            'confirmed_at' => now(),
        ]);

        // Send confirmation notification to the partner
        if ($partner->user && $payment->invoice) {
            Notification::send($partner->user, new PaymentConfirmedNotification($payment->invoice));
        }

        return back()->with('success', 'Payment confirmed successfully.');
    }

    /**
     * Reject a payment.
     */
    public function reject(Request $request, Payment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $payment->update([
            'status' => PaymentStatus::Failed,
        ]);

        // The partner is blocked until they resubmit, so they must be told.
        $partnerUser = $payment->invoice?->partner?->user;

        if ($partnerUser) {
            $partnerUser->notify(new PaymentRejectedNotification(
                $payment->load('invoice'),
                $validated['reason'] ?? null,
            ));
        }

        return back()->with('success', 'Payment has been rejected and the partner has been notified.');
    }
}
