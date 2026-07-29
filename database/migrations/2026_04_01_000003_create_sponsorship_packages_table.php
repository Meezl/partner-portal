<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsorship_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conference_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('tier');
            $table->decimal('price', 10, 2);
            $table->string('currency')->default('USD');
            $table->integer('max_partners')->nullable();
            $table->text('description')->nullable();
            $table->json('benefits')->nullable();
            $table->integer('session_slots')->default(1);
            $table->string('exhibition_space')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsorship_packages');
    }
};
