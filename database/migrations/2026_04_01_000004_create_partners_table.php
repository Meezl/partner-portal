<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conference_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('organization_name');
            $table->string('slug')->unique();
            $table->string('contact_person');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('physical_address')->nullable();
            $table->text('billing_address')->nullable();
            $table->text('tax_details')->nullable();
            $table->string('customer_code')->nullable()->unique();
            $table->string('logo_path')->nullable();
            $table->text('description')->nullable();
            $table->json('social_media')->nullable();
            $table->integer('number_of_participants')->nullable();
            $table->text('exhibition_preferences')->nullable();
            $table->string('status')->default('draft');
            $table->json('onboarding_progress')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Add foreign key constraint for partner_id on users table (column added in migration 000001)
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('partner_id')->references('id')->on('partners')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['partner_id']);
        });

        Schema::dropIfExists('partners');
    }
};
