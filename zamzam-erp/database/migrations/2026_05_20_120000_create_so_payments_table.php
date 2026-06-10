<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('so_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_bdt', 14, 2);
            $table->enum('method', [
                'cash', 'bkash', 'nagad', 'rocket', 'bank_transfer', 'cheque', 'other'
            ])->default('cash');
            $table->enum('payment_type', ['payment', 'advance'])->default('payment');
            $table->string('reference', 100)->nullable()->comment('Transaction ID, cheque no, etc.');
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->constrained('users');
            $table->timestamps();

            $table->index(['sales_order_id', 'payment_date'], 'idx_so_payments_order_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('so_payments');
    }
};
