<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bring the seeded AHAIC venue rooms in line with the "Allocation matrix
 * tables" tab (§2 Room allocation matrix) of the Session Slots & Booths
 * Scheduling Matrix workbook, which the venue issued as the authority on
 * seating capacities.
 *
 * The earlier seed took round-table figures from the workbook's older "Room
 * Details" tab, which understates the main halls (100 rather than 210/250), and
 * the previous migration's backfill carried that forward. This is venue
 * reference data, so it is corrected wholesale — matched by room name, leaving
 * any other rooms an admin has added untouched.
 */
return new class extends Migration
{
    /**
     * name => [theatre, round]. A null round capacity is the matrix's "N/A":
     * the venue does not lay that room out with round tables.
     */
    private const CAPACITIES = [
        'Auditorium' => [1200, null],
        'MH1' => [500, 210],
        'MH2' => [500, 210],
        'MH3' => [500, 210],
        'MH4' => [600, 250],
        'AD10' => [200, 80],
        'AD11' => [70, 40],
        'AD12' => [270, 130],
        'Mezzanine' => [70, null],
        'AD1' => [70, null],
        'AD3' => [30, null],
        'AD4' => [30, 30],
        // Fixed boardrooms: the seating is the boardroom table, so it counts as
        // the round-table arrangement and theatre style is not on offer.
        'AD5' => [null, 12],
        'AD6' => [null, 12],
        'AD7' => [null, 24],
        'AD8' => [null, 10],
        'AD9' => [null, 12],
        'MR1' => [null, 12],
        // Exhibition and F&B space: no seated capacity at all.
        'Foyer 1A' => [null, null],
        'Foyer 1B' => [null, null],
        'Foyer 1C' => [null, null],
        'Concourse' => [null, null],
    ];

    public function up(): void
    {
        foreach (self::CAPACITIES as $name => [$theatre, $round]) {
            DB::table('rooms')->where('name', $name)->update([
                'theatre_capacity' => $theatre,
                'round_capacity' => $round,
            ]);
        }

        // round_capacity now has a column of its own; leaving the old copy in
        // the equipment blob shows admins two different numbers for one room.
        foreach (DB::table('rooms')->select('id', 'equipment')->get() as $room) {
            $equipment = json_decode($room->equipment ?? 'null', true);

            if (! is_array($equipment) || ! array_key_exists('round_capacity', $equipment)) {
                continue;
            }

            unset($equipment['round_capacity']);

            DB::table('rooms')->where('id', $room->id)->update([
                'equipment' => json_encode($equipment),
            ]);
        }
    }

    public function down(): void
    {
        // The pre-matrix figures are not worth restoring: the previous
        // migration's backfill rebuilds them from `capacity` and the equipment
        // blob if it is re-run.
    }
};
