<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conferences', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->integer('year');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('venue');
            $table->text('description')->nullable();
            $table->date('registration_deadline')->nullable();
            $table->date('onboarding_deadline')->nullable();
            $table->date('lock_date')->nullable();
            $table->string('status')->default('draft');
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conferences');
    }
};
