<?php

namespace App\Services;

use App\Enums\AgreementStatus;
use App\Models\Agreement;
use App\Models\Partner;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class AgreementGeneratorService
{
    public function generate(Partner $partner): Agreement
    {
        $partner->loadMissing('packages');

        $package = $partner->packages->first();

        $agreement = Agreement::create([
            'partner_id' => $partner->id,
            'document_path' => null,
            'generated_at' => now(),
            'status' => AgreementStatus::Pending,
        ]);

        return $this->storePdf($agreement, $partner, false);
    }

    public function generateSignedCopy(Agreement $agreement): Agreement
    {
        $agreement->loadMissing('partner.packages');

        return $this->storePdf($agreement, $agreement->partner, true);
    }

    private function storePdf(Agreement $agreement, Partner $partner, bool $signed): Agreement
    {
        $package = $partner->packages->first();
        $pdf = Pdf::loadView('pdf.agreement', [
            'partner' => $partner,
            'package' => $package,
            'agreement' => $agreement,
        ]);

        $filename = $signed
            ? 'agreements/signed_agreement_'.$agreement->id.'_'.time().'.pdf'
            : 'agreements/agreement_'.$agreement->id.'_'.time().'.pdf';

        Storage::disk('local')->put($filename, $pdf->output());

        $agreement->update($signed
            ? ['signed_document_path' => $filename]
            : ['document_path' => $filename]);

        return $agreement->fresh();
    }
}
