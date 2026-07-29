<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number')->unique();
            $table->string('customer_code')->nullable();
            $table->date('date_of_service');
            $table->date('due_date');
            $table->decimal('amount', 12, 2);
            $table->string('currency')->default('USD');
            $table->json('benefits_summary')->nullable();
            $table->json('bank_details')->nullable();
            $table->json('additional_options')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
