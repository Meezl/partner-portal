<?php

namespace App\Http\Controllers\Partner;

use App\Enums\PartnerStatus;
use App\Http\Controllers\Controller;
use App\Notifications\AgreementReadyNotification;
use App\Services\AgreementGeneratorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommitmentController extends Controller
{
    /**
     * Show the commitment form with billing/tax fields.
     */
    public function edit(Request $request): Response|RedirectResponse
    {
        $partner = $request->user()->partner;

        if (! $partner) {
            return redirect()->route('partner.eoi.create')
                ->with('error', 'Please submit your expression of interest first.');
        }

        if (! in_array($partner->status, [PartnerStatus::InterestSubmitted, PartnerStatus::PendingAgreement])) {
            return redirect()->route('partner.dashboard')
                ->with('error', 'You cannot modify your commitment at this stage.');
        }

        $partner->load('packages');
        $selectedPackage = $partner->packages->first();

        if (! $selectedPackage) {
            return redirect()->route('partner.eoi.create')
                ->with('error', 'Please select a sponsorship package before continuing.');
        }

        return Inertia::render('Partner/Commitment', [
            'partner' => $partner,
            'selectedPackage' => $selectedPackage,
        ]);
    }

    /**
     * Update commitment details and generate agreement.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'billing_address' => ['required', 'string', 'max:500'],
            'tax_details' => ['nullable', 'string', 'max:500'],
        ]);

        $partner = $request->user()->partner;
        $partner->loadMissing('packages');

        $partner->update([
            'billing_address' => $validated['billing_address'],
            'tax_details' => $validated['tax_details'] ?? null,
            'status' => PartnerStatus::PendingAgreement,
        ]);

        $agreementService = app(AgreementGeneratorService::class);
        $agreement = $agreementService->generate($partner);

        $request->user()->notify(new AgreementReadyNotification($agreement));

        return redirect()->route('partner.agreement.show')
            ->with('success', 'Your package confirmation has been saved. Please review and sign your agreement.');
    }
}
