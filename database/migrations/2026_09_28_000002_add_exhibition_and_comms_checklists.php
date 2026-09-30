<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partners now pick their exhibition and communications needs from a checklist
 * instead of describing them in free text, and contacts carry a job title
 * alongside the portal role they fill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->json('exhibition_requirements')->nullable()->after('exhibition_preferences');
        });

        Schema::table('branding_requirements', function (Blueprint $table) {
            $table->json('comms_checklist')->nullable()->after('requirements');
        });

        Schema::table('partner_contacts', function (Blueprint $table) {
            $table->string('designation')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn('exhibition_requirements');
        });

        Schema::table('branding_requirements', function (Blueprint $table) {
            $table->dropColumn('comms_checklist');
        });

        Schema::table('partner_contacts', function (Blueprint $table) {
            $table->dropColumn('designation');
        });
    }
};
