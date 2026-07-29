<?php

namespace App\Enums;

enum SessionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Scheduled => 'Scheduled',
            self::Confirmed => 'Confirmed',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'text-gray-500',
            self::Submitted => 'text-blue-500',
            self::Scheduled => 'text-indigo-500',
            self::Confirmed => 'text-green-600',
            self::Completed => 'text-green-700',
            self::Cancelled => 'text-red-500',
        };
    }
}
