<?php

namespace App\Http\Controllers\Partner;

use App\Enums\AgreementStatus;
use App\Enums\PartnerStatus;
use App\Http\Controllers\Controller;
use App\Models\Agreement;
use App\Models\Invoice;
use App\Notifications\AgreementSignedNotification;
use App\Notifications\InvoiceSentNotification;
use App\Services\AgreementGeneratorService;
use App\Services\InvoiceGeneratorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AgreementController extends Controller
{
    /**
     * Show the agreement for the partner.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $partner = $request->user()->partner;
        $agreement = $partner->agreements()->latest()->first();
        $invoice = $partner->invoices()->latest()->first();

        if (! $agreement) {
            return redirect()->route('partner.commitment.edit')
                ->with('error', 'Please confirm your sponsorship package first.');
        }

        return Inertia::render('Partner/Agreement', [
            'partner' => $partner,
            'agreement' => $agreement,
            'invoice' => $invoice,
        ]);
    }

    /**
     * Download the agreement PDF file from storage.
     */
    public function download(Request $request): BinaryFileResponse
    {
        $partner = $request->user()->partner;
        $agreement = $partner->agreements()->latest()->first();
        $path = $agreement?->signed_document_path ?: $agreement?->document_path;

        if (! $agreement || ! $path) {
            abort(404, 'Agreement document not found.');
        }

        return response()->download(Storage::disk('local')->path($path));
    }

    /**
     * Digitally sign the agreement in the portal.
     */
    public function sign(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'signer_name' => ['required', 'string', 'max:255'],
            'accept_terms' => ['accepted'],
        ]);

        $partner = $request->user()->partner;
        $agreement = $partner->agreements()->latest()->first();

        if (! $agreement) {
            return redirect()->route('partner.commitment.edit')
                ->with('error', 'Please generate your agreement before signing.');
        }

        $agreement->update([
            'signed_by_name' => $validated['signer_name'],
            'signed_method' => 'digital',
            'signed_at' => now(),
            'status' => AgreementStatus::Signed,
        ]);

        app(AgreementGeneratorService::class)->generateSignedCopy($agreement->fresh(['partner.packages']));
        $this->completeAgreement($request, $partner->id);

        // Finance has no other prompt that an invoice is now outstanding.
        Notification::route('mail', array_values(array_filter(
            (array) (config('ahaic.team_emails') ?: [config('ahaic.central_email')])
        )))->notify(new AgreementSignedNotification($agreement->load('partner')));

        return back()->with('success', 'Your agreement has been digitally signed and your invoice is now ready.');
    }

    /**
     * Upload the signed agreement document.
     */
    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'signed_document' => ['required', 'mimes:pdf', 'max:10240'],
        ]);

        $partner = $request->user()->partner;
        $agreement = $partner->agreements()->latest()->first();

        if (! $agreement) {
            return redirect()->route('partner.commitment.edit')
                ->with('error', 'Please generate your agreement before uploading a signed copy.');
        }

        $path = $request->file('signed_document')->store("agreements/{$partner->id}", 'local');

        $agreement->update([
            'signed_document_path' => $path,
            'signed_by_name' => $partner->contact_person,
            'signed_method' => 'upload',
            'signed_at' => now(),
            'status' => AgreementStatus::Signed,
        ]);

        $this->completeAgreement($request, $partner->id);

        return back()->with('success', 'Your signed agreement has been uploaded successfully and your invoice is now ready.');
    }

    private function completeAgreement(Request $request, int $partnerId): void
    {
        $partner = $request->user()->partner?->fresh(['invoices', 'user', 'packages']);

        if (! $partner || $partner->id !== $partnerId) {
            return;
        }

        $invoice = $partner->invoices
            ->sortByDesc('created_at')
            ->first(fn (Invoice $invoice) => filled($invoice->document_path));

        if (! $invoice) {
            $invoice = app(InvoiceGeneratorService::class)->generate($partner);

            if ($request->user()) {
                $request->user()->notify(new InvoiceSentNotification($invoice));
            }
        }

        $partner->update(['status' => PartnerStatus::PendingPayment]);
    }
}
