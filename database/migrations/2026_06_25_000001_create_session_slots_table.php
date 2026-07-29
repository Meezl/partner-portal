<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conference_id')->constrained()->cascadeOnDelete();
            $table->string('slot_code');               // e.g. "Parallel 1", "Breakfast 3"
            $table->string('slot_category');           // plenary|pop|breakfast|parallel|reception|other
            $table->string('track_label')->nullable(); // e.g. "Parallel Track 1", "BT 2", "RT 1"
            $table->unsignedTinyInteger('day_index'); // 0..3
            $table->date('date')->nullable();
            $table->string('time_label');              // "08:00-09:30"
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->foreignId('default_room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('default_format')->nullable();
            $table->unsignedInteger('capacity_hint')->nullable();
            $table->boolean('is_assignable')->default(true);
            $table->foreignId('claimed_by_session_id')->nullable()->constrained('conference_sessions')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['conference_id', 'slot_code']);
            $table->index(['conference_id', 'day_index']);
        });

        Schema::table('conference_sessions', function (Blueprint $table) {
            $table->foreignId('session_slot_id')->nullable()->after('communications_lead_id')
                ->constrained('session_slots')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conference_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('session_slot_id');
        });

        Schema::dropIfExists('session_slots');
    }
};
