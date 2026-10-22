<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A room and time the team allocate is not settled until the partner says it
 * works for them, so the booking records when they confirmed it.
 *
 * Null means "not confirmed yet" — which is also where a booking goes back to
 * whenever the team move it, since the partner has not seen the new time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_schedules', function (Blueprint $table) {
            $table->timestamp('confirmed_by_partner_at')->nullable()->after('assigned_by');
        });
    }

    public function down(): void
    {
        Schema::table('session_schedules', function (Blueprint $table) {
            $table->dropColumn('confirmed_by_partner_at');
        });
    }
};
