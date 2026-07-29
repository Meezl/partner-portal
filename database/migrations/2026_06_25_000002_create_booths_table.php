<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conference_id')->constrained()->cascadeOnDelete();
            $table->string('zone');               // e.g. "Foyer 1A"
            $table->string('booth_number');       // e.g. "1", "26"
            $table->string('size')->default("3x3");
            $table->string('status')->default('available'); // available|reserved|assigned|blocked
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['conference_id', 'zone', 'booth_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booths');
    }
};
