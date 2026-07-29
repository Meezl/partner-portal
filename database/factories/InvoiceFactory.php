<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),
            'invoice_number' => 'AHAIC'.now()->year.'INV'.$this->faker->unique()->numerify('##'),
            'customer_code' => 'AHAIC-CUST-'.$this->faker->unique()->numerify('#####'),
            'document_path' => 'invoices/invoice.pdf',
            'date_of_service' => now(),
            'due_date' => now()->addDays(30),
            'amount' => 25000,
            'currency' => 'USD',
            'benefits_summary' => ['Main stage visibility'],
            'bank_details' => [
                'bank_name' => 'Standard Chartered Bank',
                'account_name' => 'Amref Health Africa',
            ],
            'additional_options' => [
                'package_name' => 'Gold Partnership',
            ],
            'status' => InvoiceStatus::Sent,
            'paid_at' => null,
            'sent_at' => now(),
            'notes' => 'Please quote the invoice number when paying.',
        ];
    }
}
