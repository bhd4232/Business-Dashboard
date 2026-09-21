<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One line of a PayoutBatch — polymorphic `payable` points at the real
     * payout record (SettlementPayout or ChannelPartnerPayout in Phase 1;
     * ResellerCommission once the Reseller phase is built). Recipient bank
     * fields are a snapshot taken when the item is added to a batch, so a
     * later change to the investor's stored bank details never rewrites the
     * record of what was actually sent.
     */
    public function up(): void
    {
        Schema::create('payout_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payout_batch_id')->constrained()->cascadeOnDelete();
            $table->morphs('payable');
            $table->string('recipient_name');
            $table->string('recipient_bank_name')->nullable();
            $table->string('recipient_branch')->nullable();
            $table->string('recipient_routing_number')->nullable();
            $table->string('recipient_account_number')->nullable();
            $table->decimal('amount', 15, 2);
            $table->enum('status', ['pending', 'in_batch', 'paid', 'failed', 'returned'])->default('pending');
            $table->string('failure_reason')->nullable();
            $table->string('bank_transaction_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_items');
    }
};
