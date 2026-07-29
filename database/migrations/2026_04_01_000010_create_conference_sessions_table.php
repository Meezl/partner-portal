<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conference_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conference_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('format');
            $table->json('organizers')->nullable();
            $table->json('co_hosts')->nullable();
            $table->string('target_audience')->nullable();
            $table->integer('expected_participants')->nullable();
            $table->boolean('is_open')->default(true);
            $table->json('special_requirements')->nullable();
            $table->foreignId('session_lead_id')->nullable()->constrained('partner_contacts')->nullOnDelete();
            $table->foreignId('communications_lead_id')->nullable()->constrained('partner_contacts')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conference_sessions');
    }
};
