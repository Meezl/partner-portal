<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sponsorship_packages', function (Blueprint $table) {
            $table->json('complimentary_registrations')->nullable()->after('exhibition_space');
            $table->json('thought_leadership')->nullable()->after('benefits');
            $table->json('visibility')->nullable()->after('thought_leadership');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sponsorship_packages', function (Blueprint $table) {
            $table->dropColumn(['complimentary_registrations', 'thought_leadership', 'visibility']);
        });
    }
};
