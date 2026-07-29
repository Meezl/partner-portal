<?php

namespace App\Enums;

enum PackageTier: string
{
    case Diamond = 'diamond';
    case Platinum = 'platinum';
    case Gold = 'gold';
    case Silver = 'silver';
    case Cso = 'cso';
    case Exhibitor = 'exhibitor';

    public function label(): string
    {
        return match ($this) {
            self::Diamond => 'Diamond',
            self::Platinum => 'Platinum',
            self::Gold => 'Gold',
            self::Silver => 'Silver',
            self::Cso => 'CSO',
            self::Exhibitor => 'Exhibitor',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Diamond => '#b9f2ff',
            self::Platinum => '#e5e4e2',
            self::Gold => '#ffd700',
            self::Silver => '#c0c0c0',
            self::Cso => '#255325',
            self::Exhibitor => '#0a9fa5',
        };
    }
}
