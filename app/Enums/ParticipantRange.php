<?php

namespace App\Enums;

/**
 * The headcount bands a partner picks from when proposing a session. Sessions
 * used to carry an exact number, which partners were guessing at months ahead;
 * the bands are what the programme team actually plan around.
 */
enum ParticipantRange: string
{
    case From5To10 = '5-10';
    case From10To15 = '10-15';
    case From15To30 = '15-30';
    case From30To50 = '30-50';
    case From50To80 = '50-80';
    case From80To100 = '80-100';
    case From100To150 = '100-150';
    case Over150 = 'over 150';

    public function label(): string
    {
        return match ($this) {
            self::Over150 => 'Over 150',
            default => $this->value,
        };
    }

    /**
     * The band an exact headcount falls into, used to carry the old numeric
     * answers over. Boundaries are shared between bands, so a number on one
     * reads as the top of the lower band.
     */
    public static function fromCount(?int $count): ?self
    {
        return match (true) {
            $count === null, $count <= 0 => null,
            $count <= 10 => self::From5To10,
            $count <= 15 => self::From10To15,
            $count <= 30 => self::From15To30,
            $count <= 50 => self::From30To50,
            $count <= 80 => self::From50To80,
            $count <= 100 => self::From80To100,
            $count <= 150 => self::From100To150,
            default => self::Over150,
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
