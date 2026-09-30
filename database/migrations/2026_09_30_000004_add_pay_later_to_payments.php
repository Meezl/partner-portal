<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A partner either pays now and sends proof, or commits to pay later and sends
 * a local purchase order. Both land in the same queue for finance, so they are
 * the same record with a type on it rather than two parallel flows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_type')->default('proof_of_payment')->after('partner_id');
        });

        // Everything recorded before this was money already sent.
        DB::table('payments')->update(['payment_type' => 'proof_of_payment']);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('payment_type');
        });
    }
};
