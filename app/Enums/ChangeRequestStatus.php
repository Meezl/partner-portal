<?php

namespace App\Enums;

enum ChangeRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case AutoResolved = 'auto_resolved';
    /** Logged for the team to action by hand; nothing was changed by approving it. */
    case Noted = 'noted';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::AutoResolved => 'Auto Resolved',
            self::Noted => 'Noted',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'text-yellow-600',
            self::Approved => 'text-green-600',
            self::Rejected => 'text-red-600',
            self::AutoResolved => 'text-blue-500',
            self::Noted => 'text-amber-600',
        };
    }
}
