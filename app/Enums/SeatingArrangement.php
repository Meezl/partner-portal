<?php

namespace App\Enums;

/**
 * The two seating arrangements the venue sets up. Every bookable room in the
 * AHAIC allocation matrix is costed for theatre style and, where the room
 * allows it, round tables — nothing else is offered, so a session picks one of
 * these and the room's capacity in that arrangement decides what fits.
 */
enum SeatingArrangement: string
{
    case Theatre = 'theatre';
    case RoundTable = 'round_table';

    /**
     * The option name a partner picks from.
     */
    public function label(): string
    {
        return match ($this) {
            self::Theatre => 'Theatre style',
            self::RoundTable => 'Round Tables',
        };
    }

    /**
     * The form used inside a sentence, where the arrangement qualifies
     * something else — "round table capacity", "seats 210 round table".
     */
    public function describe(): string
    {
        return match ($this) {
            self::Theatre => 'theatre style',
            self::RoundTable => 'round table',
        };
    }

    /**
     * The rooms column holding capacity for this arrangement.
     */
    public function capacityColumn(): string
    {
        return match ($this) {
            self::Theatre => 'theatre_capacity',
            self::RoundTable => 'round_capacity',
        };
    }

    /**
     * Map the free-text seating values stored before this enum existed
     * ("theater", "classroom", "u_shape", "round_tables", "boardroom").
     */
    public static function fromLegacy(?string $value): self
    {
        return match (strtolower(trim((string) $value))) {
            'round_table', 'round_tables', 'round', 'boardroom', 'u_shape' => self::RoundTable,
            default => self::Theatre,
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
