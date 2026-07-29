<?php

namespace Database\Factories;

use App\Enums\AgreementStatus;
use App\Models\Agreement;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agreement>
 */
class AgreementFactory extends Factory
{
    protected $model = Agreement::class;

    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),
            'document_path' => 'agreements/agreement.pdf',
            'signed_document_path' => null,
            'signed_by_name' => null,
            'signed_method' => null,
            'signed_at' => null,
            'generated_at' => now(),
            'status' => AgreementStatus::Pending,
        ];
    }
}
