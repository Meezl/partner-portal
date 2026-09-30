<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Signing in the portal now captures a drawn signature and the witness the
 * agreement's own signature block has always asked for, so a digitally signed
 * copy carries the same detail as a wet-signed one.
 *
 * Signature images are small PNG data URLs drawn in the browser; they live
 * beside the record they belong to so the PDF can embed them directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->string('signed_by_title')->nullable()->after('signed_by_name');
            $table->longText('signature_image')->nullable()->after('signed_by_title');
            $table->string('witness_name')->nullable()->after('signature_image');
            $table->string('witness_title')->nullable()->after('witness_name');
            $table->longText('witness_signature_image')->nullable()->after('witness_title');
        });

        Schema::table('partners', function (Blueprint $table) {
            // The signatory's job title, captured at registration and used as
            // the Title on the agreement's signature block.
            $table->string('contact_title')->nullable()->after('contact_person');
        });
    }

    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropColumn([
                'signed_by_title',
                'signature_image',
                'witness_name',
                'witness_title',
                'witness_signature_image',
            ]);
        });

        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn('contact_title');
        });
    }
};
