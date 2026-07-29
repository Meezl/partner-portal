<?php

namespace App\Enums;

enum AgreementStatus: string
{
    case Pending = 'pending';
    case Signed = 'signed';
    case Verified = 'verified';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Signed => 'Signed',
            self::Verified => 'Verified',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'text-yellow-600',
            self::Signed => 'text-blue-500',
            self::Verified => 'text-green-600',
        };
    }
}
