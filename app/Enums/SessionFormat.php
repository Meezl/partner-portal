<?php

namespace App\Enums;

enum SessionFormat: string
{
    case Roundtable = 'roundtable';
    case Panel = 'panel';
    case FiresideChat = 'fireside_chat';
    case Keynote = 'keynote';
    case Workshop = 'workshop';
    case InteractiveDialogue = 'interactive_dialogue';
    case LiveStudio = 'live_studio';
    case StandUp = 'stand_up';
    case Breakout = 'breakout';
    case Networking = 'networking';
    case Cocktail = 'cocktail';
    case Showcase = 'showcase';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Roundtable => 'Roundtable',
            self::Panel => 'Panel Discussion',
            self::FiresideChat => 'Fireside Chat',
            self::Keynote => 'Keynote / Featured Address',
            self::Workshop => 'Workshop / Masterclass',
            self::InteractiveDialogue => 'Interactive Dialogue',
            self::LiveStudio => 'Live Studio Session',
            self::StandUp => 'Stand-up Session',
            self::Breakout => 'Breakout Session',
            self::Networking => 'Networking / Reception',
            self::Cocktail => 'Cocktail',
            self::Showcase => 'Product / Solution Showcase',
            self::Other => 'Other',
        };
    }
}
