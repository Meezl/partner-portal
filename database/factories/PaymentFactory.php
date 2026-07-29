<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'partner_id' => Partner::factory(),
            'amount' => 25000,
            'currency' => 'USD',
            'payment_method' => 'bank_transfer',
            'transaction_reference' => $this->faker->unique()->bothify('TXN-#####'),
            'supporting_document_path' => 'payments/test/payment-proof.pdf',
            'status' => PaymentStatus::Pending,
            'confirmed_by' => null,
            'confirmed_at' => null,
        ];
    }
}
