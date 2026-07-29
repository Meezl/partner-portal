<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['partner_id', 'invoice_number', 'customer_code', 'document_path', 'date_of_service', 'due_date', 'amount', 'currency', 'benefits_summary', 'bank_details', 'additional_options', 'status', 'paid_at', 'sent_at', 'notes'])]
class Invoice extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'date_of_service' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'benefits_summary' => 'array',
            'bank_details' => 'array',
            'additional_options' => 'array',
            'paid_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
