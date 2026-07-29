<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('agreements', 'signed_by_name') || ! Schema::hasColumn('agreements', 'signed_method')) {
            Schema::table('agreements', function (Blueprint $table) {
                if (! Schema::hasColumn('agreements', 'signed_by_name')) {
                    $table->string('signed_by_name')->nullable()->after('signed_document_path');
                }

                if (! Schema::hasColumn('agreements', 'signed_method')) {
                    $table->string('signed_method')->nullable()->after('signed_by_name');
                }
            });
        }

        if (! Schema::hasColumn('invoices', 'document_path')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('document_path')->nullable()->after('customer_code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('agreements', 'signed_by_name') || Schema::hasColumn('agreements', 'signed_method')) {
            Schema::table('agreements', function (Blueprint $table) {
                $columns = [];

                if (Schema::hasColumn('agreements', 'signed_by_name')) {
                    $columns[] = 'signed_by_name';
                }

                if (Schema::hasColumn('agreements', 'signed_method')) {
                    $columns[] = 'signed_method';
                }

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasColumn('invoices', 'document_path')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('document_path');
            });
        }
    }
};
