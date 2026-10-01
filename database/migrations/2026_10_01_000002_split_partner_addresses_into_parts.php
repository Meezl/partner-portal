<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Collect the partner's physical and billing addresses as four fields each —
 * address, city, postal code and country — rather than one free-text blob, so
 * invoices and the partnership agreement can lay an address out properly.
 *
 * The existing `physical_address` / `billing_address` columns stay and become
 * the street-address line, which is what partners have been typing into them.
 * The parts a partner never gave stay null until they next edit the form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->string('physical_city')->nullable()->after('physical_address');
            $table->string('physical_postal_code')->nullable()->after('physical_city');
            $table->string('physical_country')->nullable()->after('physical_postal_code');

            $table->string('billing_city')->nullable()->after('billing_address');
            $table->string('billing_postal_code')->nullable()->after('billing_city');
            $table->string('billing_country')->nullable()->after('billing_postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn([
                'physical_city',
                'physical_postal_code',
                'physical_country',
                'billing_city',
                'billing_postal_code',
                'billing_country',
            ]);
        });
    }
};
