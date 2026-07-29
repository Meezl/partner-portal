<?php

namespace App\Enums;

enum ChangeRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case AutoResolved = 'auto_resolved';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::AutoResolved => 'Auto Resolved',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'text-yellow-600',
            self::Approved => 'text-green-600',
            self::Rejected => 'text-red-600',
            self::AutoResolved => 'text-blue-500',
        };
    }
}
