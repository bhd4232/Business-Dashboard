<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per delivered order placed through a reseller's storefront.
     * Created in 'holding' the moment an order is marked delivered
     * (App\Observers\ResellerCommissionObserver); promoted to 'payable' by
     * a daily per-company command once company_payout_settings.
     * reseller_commission_hold_days has passed with no return; reversed if
     * the order is returned/refunded while still holding/payable (never
     * once paid -- see the service for why). See
     * 10_INVESTOR_RESELLER_AUTO_PAYOUT_PLAN.md.
     */
    public function up(): void
    {
        Schema::create('reseller_commissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete()->unique();
            $table->foreignId('reseller_customer_id')->constrained('customers')->cascadeOnDelete();
            $table->decimal('gross_margin_amount', 15, 2);
            $table->decimal('other_costs_amount', 15, 2)->default(0);
            $table->decimal('commission_amount', 15, 2);
            // [{label, amount}, ...] -- itemized deductions (packaging, return
            // handling, courier/delivery charge, or anything else staff adds)
            // subtracted to reach commission_amount. Editable by staff any
            // time before the batch that pays it is approved.
            $table->json('cost_breakdown')->nullable();
            $table->enum('status', ['holding', 'payable', 'in_batch', 'paid', 'reversed'])->default('holding');
            $table->date('order_delivered_at');
            $table->date('holding_until');
            $table->timestamp('promoted_to_payable_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'holding_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_commissions');
    }
};
