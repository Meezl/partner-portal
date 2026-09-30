<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storage names an uploaded logo by hash, so a reviewer downloading several
 * partners' logos ends up with a folder of gibberish. Keep the name the partner
 * gave the file, as branding assets already do.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->string('logo_name')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn('logo_name');
        });
    }
};
