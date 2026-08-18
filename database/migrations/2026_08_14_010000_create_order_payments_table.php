<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('advance');
            $table->string('method', 50)->default('cash');
            $table->decimal('amount', 12, 2);
            $table->text('note')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('paid_at');
            $table->timestamps();

            $table->index(['order_id', 'paid_at']);
            $table->index(['company_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payments');
    }
};
