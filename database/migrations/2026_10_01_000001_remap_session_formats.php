<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Replace the original six session formats with the thirteen the programme
 * team now offers partners (App\Enums\SessionFormat).
 *
 * `panel`, `workshop` and `roundtable` keep their slugs, so only the three
 * retired ones need moving. Stored values outside the enum would break the
 * model's enum cast, so both the sessions' own format and the rooms'
 * format_suitability lists are remapped.
 */
return new class extends Migration
{
    private const REMAP = [
        'plenary' => 'keynote',
        'exhibition' => 'showcase',
        'side_event' => 'networking',
    ];

    public function up(): void
    {
        $this->remap(self::REMAP);
    }

    public function down(): void
    {
        $this->remap(array_flip(self::REMAP));
    }

    /**
     * @param  array<string, string>  $map
     */
    private function remap(array $map): void
    {
        foreach ($map as $from => $to) {
            DB::table('conference_sessions')->where('format', $from)->update(['format' => $to]);
        }

        foreach (DB::table('rooms')->select('id', 'format_suitability')->get() as $room) {
            $formats = json_decode($room->format_suitability ?? 'null', true);

            if (! is_array($formats)) {
                continue;
            }

            $remapped = array_values(array_unique(array_map(
                fn ($format) => $map[$format] ?? $format,
                $formats,
            )));

            if ($remapped === $formats) {
                continue;
            }

            DB::table('rooms')->where('id', $room->id)->update([
                'format_suitability' => json_encode($remapped),
            ]);
        }
    }
};
