<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The communications section now asks what a partner is planning around AHAIC
 * — research, a programme, a report, a partnership — and for a paragraph or
 * two about it, in place of the "what do you need from communications?"
 * checklist.
 *
 * The old `comms_checklist` and `requirements` columns stay with their data:
 * the form no longer writes them, but the answers partners already gave are
 * still worth reading.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branding_requirements', function (Blueprint $table) {
            $table->json('ahaic_plans')->nullable()->after('requirements');
            $table->text('plans_summary')->nullable()->after('ahaic_plans');
        });
    }

    public function down(): void
    {
        Schema::table('branding_requirements', function (Blueprint $table) {
            $table->dropColumn(['ahaic_plans', 'plans_summary']);
        });
    }
};
