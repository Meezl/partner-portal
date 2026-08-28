<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conference_sessions', function (Blueprint $table) {
            // The slot a partner has asked for but the partnerships team has
            // not yet decided on. session_slot_id stays the *approved* slot.
            $table->foreignId('requested_session_slot_id')->nullable()->after('session_slot_id')
                ->constrained('session_slots')->nullOnDelete();
        });

        Schema::table('session_slots', function (Blueprint $table) {
            // A soft reservation held while a time request awaits review, so
            // two partners cannot be approved into the same slot.
            $table->foreignId('held_by_session_id')->nullable()->after('claimed_at')
                ->constrained('conference_sessions')->nullOnDelete();
            $table->timestamp('held_at')->nullable()->after('held_by_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('session_slots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('held_by_session_id');
            $table->dropColumn('held_at');
        });

        Schema::table('conference_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_session_slot_id');
        });
    }
};
