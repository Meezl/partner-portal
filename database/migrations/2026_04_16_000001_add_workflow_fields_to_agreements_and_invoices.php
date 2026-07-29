<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->string('signed_by_name')->nullable()->after('signed_document_path');
            $table->string('signed_method')->nullable()->after('signed_by_name');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('document_path')->nullable()->after('customer_code');
        });
    }

    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropColumn(['signed_by_name', 'signed_method']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('document_path');
        });
    }
};
