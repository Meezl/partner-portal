<?php

namespace App\Models;

use App\Enums\AgreementStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['partner_id', 'document_path', 'signed_document_path', 'signed_by_name', 'signed_method', 'signed_at', 'generated_at', 'status', 'reviewed_by', 'reviewed_at', 'review_notes'])]
class Agreement extends Model
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
            'status' => AgreementStatus::class,
            'signed_at' => 'datetime',
            'generated_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Whether the partner may (re)sign: before a first signature, or after
     * the team sent a signed copy back.
     */
    public function awaitsSignature(): bool
    {
        return in_array($this->status, [AgreementStatus::Pending, AgreementStatus::Rejected], true);
    }
}
