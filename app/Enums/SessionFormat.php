<?php

namespace App\Enums;

enum SessionFormat: string
{
    case Panel = 'panel';
    case Workshop = 'workshop';
    case Plenary = 'plenary';
    case Roundtable = 'roundtable';
    case Exhibition = 'exhibition';
    case SideEvent = 'side_event';

    public function label(): string
    {
        return match ($this) {
            self::Panel => 'Panel',
            self::Workshop => 'Workshop',
            self::Plenary => 'Plenary',
            self::Roundtable => 'Roundtable',
            self::Exhibition => 'Exhibition',
            self::SideEvent => 'Side Event',
        };
    }
}
