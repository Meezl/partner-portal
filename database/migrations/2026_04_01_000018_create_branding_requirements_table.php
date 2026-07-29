<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branding_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->text('requirements')->nullable();
            $table->string('media_contact_name')->nullable();
            $table->string('media_contact_email')->nullable();
            $table->string('media_contact_phone')->nullable();
            $table->json('assets')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branding_requirements');
    }
};
