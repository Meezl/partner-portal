<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seating is now either theatre style or round table, and room allocation
 * depends on how many people a room holds in the arrangement the partner asked
 * for. Those two figures used to live in the rooms.equipment JSON blob
 * (theatre implicitly as `capacity`), which cannot be queried; promote them to
 * columns so slot filtering can use them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedInteger('theatre_capacity')->nullable()->after('capacity');
            $table->unsignedInteger('round_capacity')->nullable()->after('theatre_capacity');
        });

        foreach (DB::table('rooms')->select('id', 'capacity', 'equipment')->get() as $room) {
            $equipment = json_decode($room->equipment ?? '[]', true) ?: [];
            $round = $equipment['round_capacity'] ?? null;

            DB::table('rooms')->where('id', $room->id)->update([
                'theatre_capacity' => $room->capacity ?: null,
                'round_capacity' => is_numeric($round) ? (int) $round : null,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['theatre_capacity', 'round_capacity']);
        });
    }
};
