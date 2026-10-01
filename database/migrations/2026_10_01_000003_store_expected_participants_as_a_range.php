<?php

use App\Enums\ParticipantRange;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sessions now declare a headcount band ("30-50") rather than an exact number,
 * so the column becomes a string and the numbers already captured are mapped
 * onto the band they fall into.
 */
return new class extends Migration
{
    public function up(): void
    {
        $counts = DB::table('conference_sessions')
            ->whereNotNull('expected_participants')
            ->pluck('expected_participants', 'id');

        Schema::table('conference_sessions', function (Blueprint $table) {
            $table->string('expected_participants', 20)->nullable()->change();
        });

        foreach ($counts as $id => $count) {
            DB::table('conference_sessions')->where('id', $id)->update([
                'expected_participants' => ParticipantRange::fromCount((int) $count)?->value,
            ]);
        }
    }

    public function down(): void
    {
        // The bands are coarser than the numbers they replaced, so going back
        // keeps the lower bound rather than inventing a count.
        $ranges = DB::table('conference_sessions')
            ->whereNotNull('expected_participants')
            ->pluck('expected_participants', 'id');

        foreach ($ranges as $id => $range) {
            DB::table('conference_sessions')->where('id', $id)->update([
                'expected_participants' => (int) filter_var($range, FILTER_SANITIZE_NUMBER_INT) ?: null,
            ]);
        }

        Schema::table('conference_sessions', function (Blueprint $table) {
            $table->integer('expected_participants')->nullable()->change();
        });
    }
};
