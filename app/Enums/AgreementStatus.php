<?php

namespace App\Enums;

enum AgreementStatus: string
{
    case Pending = 'pending';
    case Signed = 'signed';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Signed => 'Signed',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'text-yellow-600',
            self::Signed => 'text-blue-500',
            self::Verified => 'text-green-600',
            self::Rejected => 'text-red-600',
        };
    }
}
