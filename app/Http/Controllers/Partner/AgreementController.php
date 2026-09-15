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
use Symfony\Component\HttpFoundation\StreamedResponse;

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
    public function download(Request $request): StreamedResponse
    {
        $partner = $request->user()->partner;
        $agreement = $partner->agreements()->latest()->first();
        $path = $agreement?->signed_document_path ?: $agreement?->document_path;

        if (! $agreement || ! $path) {
            abort(404, 'Agreement document not found.');
        }

        return Storage::disk(config('ahaic.disks.private'))->download($path);
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

        if ($refusal = $this->alreadySigned($agreement)) {
            return $refusal;
        }

        $agreement->update([
            'signed_by_name' => $validated['signer_name'],
            'signed_method' => 'digital',
            'signed_at' => now(),
            'status' => AgreementStatus::Signed,
        ]);

        app(AgreementGeneratorService::class)->generateSignedCopy($agreement->fresh(['partner.packages']));
        $this->completeAgreement($request, $partner->id);
        $this->notifyTeam($agreement);

        return back()->with('success', 'Your agreement has been digitally signed and your invoice is now ready.');
    }

    /**
     * Upload the signed agreement document.
     */
    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'signed_document' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ], [
            'signed_document.required' => 'Choose the signed agreement to upload.',
            'signed_document.mimes' => 'Upload the signed agreement as a PDF — scan or save all signed pages into one PDF file.',
            'signed_document.max' => 'The signed agreement must be 10 MB or smaller.',
        ]);

        $partner = $request->user()->partner;
        $agreement = $partner->agreements()->latest()->first();

        if (! $agreement) {
            return redirect()->route('partner.commitment.edit')
                ->with('error', 'Please generate your agreement before uploading a signed copy.');
        }

        if ($refusal = $this->alreadySigned($agreement)) {
            return $refusal;
        }

        $path = $request->file('signed_document')->store("agreements/{$partner->id}", config('ahaic.disks.private'));

        $agreement->update([
            'signed_document_path' => $path,
            'signed_by_name' => $partner->contact_person,
            'signed_method' => 'upload',
            'signed_at' => now(),
            'status' => AgreementStatus::Signed,
        ]);

        $this->completeAgreement($request, $partner->id);
        $this->notifyTeam($agreement);

        return back()->with('success', 'Your signed agreement has been uploaded successfully and your invoice is now ready.');
    }

    /**
     * An agreement is signed once, by one method. A second signature — a
     * digital one over an uploaded wet-signed copy, or a re-upload — would
     * silently replace the document the team has already been told about.
     * Only the partnerships team sending it back reopens it for signing.
     */
    private function alreadySigned(Agreement $agreement): ?RedirectResponse
    {
        if ($agreement->awaitsSignature()) {
            return null;
        }

        return back()->with('error', 'This agreement has already been signed. Contact the AHAIC team if the signed copy needs to be replaced.');
    }

    /**
     * Finance has no other prompt that an invoice is now outstanding, however
     * the agreement was signed.
     */
    private function notifyTeam(Agreement $agreement): void
    {
        Notification::route('mail', array_values(array_filter(
            (array) (config('ahaic.team_emails') ?: [config('ahaic.central_email')])
        )))->notify(new AgreementSignedNotification($agreement->fresh('partner')));
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

        // Signing moves the partner on to payment, but never back: a partner
        // re-signing a rejected agreement may already have paid.
        if ($partner->status === PartnerStatus::PendingAgreement) {
            $partner->update(['status' => PartnerStatus::PendingPayment]);
        }
    }
}
