<?php

use App\Models\ConferenceSession;
use App\Services\SessionScheduleSynchroniser;
use Illuminate\Database\Migrations\Migration;

/**
 * One-off reconciliation of the two scheduling systems.
 *
 * Before the slot matrix and the scheduling board were bridged they were
 * maintained independently, so some sessions ended up holding a SessionSlot
 * saying one thing and a SessionSchedule saying another. The slot is now the
 * source of truth, so every session that has one gets its board booking
 * rebuilt from it. Bookings made directly on the board with no slot behind them
 * are left untouched — there is nothing to derive them from.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sync = app(SessionScheduleSynchroniser::class);

        ConferenceSession::whereNotNull('session_slot_id')
            ->with('sessionSlot')
            ->chunkById(100, function ($sessions) use ($sync) {
                foreach ($sessions as $session) {
                    $sync->sync($session);
                }
            });
    }

    public function down(): void
    {
        // Rebuilding a booking from its slot is not reversible: the previous,
        // drifted room/time is not recorded anywhere. Nothing to undo.
    }
};
