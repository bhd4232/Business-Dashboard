<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('direction', 10);
            $table->decimal('amount', 12, 2);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->date('transaction_date');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'transaction_date']);
            $table->index(['type', 'direction']);
            $table->index(['reference_type', 'reference_id'], 'transaction_ledgers_reference_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_ledgers');
    }
};
