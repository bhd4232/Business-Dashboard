<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('project_code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('deal_reference')->nullable();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('duration_type', ['2_month', '6_month', '12_month', 'custom_days'])->default('custom_days');
            $table->unsignedInteger('trade_cycle_days')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('target_amount', 15, 2)->nullable();
            $table->decimal('investor_share_percent', 5, 2)->default(40);
            $table->decimal('channel_partner_share_percent', 5, 2)->default(10);
            $table->decimal('company_share_percent', 5, 2)->default(50);
            $table->enum('status', ['open', 'running', 'closed', 'settled'])->default('open');
            $table->timestamps();
            $table->unique(['company_id', 'project_code']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('investors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('guardian_name')->nullable();
            $table->string('phone');
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('nid_number')->nullable();
            $table->foreignId('channel_partner_id')->nullable()->constrained('investors')->nullOnDelete();
            $table->text('channel_partner_change_reason')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'phone']);
        });

        Schema::create('investments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('investment_projects')->cascadeOnDelete();
            $table->foreignId('investor_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->enum('payment_method', ['cash', 'bkash', 'bank', 'other']);
            $table->string('payment_reference')->nullable();
            $table->date('invested_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'project_id']);
        });

        Schema::create('project_cost_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('investment_projects')->cascadeOnDelete();
            $table->enum('category', ['landed_cost', 'local_expense']);
            $table->string('label');
            $table->decimal('amount', 15, 2);
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'project_id', 'category']);
        });

        Schema::create('investor_security_instruments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investment_id')->constrained()->cascadeOnDelete();
            $table->string('cheque_number')->nullable();
            $table->string('cheque_bank_name')->nullable();
            $table->decimal('cheque_amount', 15, 2)->nullable();
            $table->enum('cheque_status', ['held_by_investor', 'returned', 'cashed'])->default('held_by_investor');
            $table->string('guarantor_name')->nullable();
            $table->string('guarantor_nid')->nullable();
            $table->string('guarantor_phone')->nullable();
            $table->string('contract_document_path')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'investment_id']);
        });

        Schema::create('project_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('investment_projects')->cascadeOnDelete()->unique();
            $table->decimal('total_revenue', 15, 2);
            $table->decimal('total_cost', 15, 2);
            $table->decimal('net_profit', 15, 2);
            $table->decimal('investor_pool_amount', 15, 2);
            $table->decimal('channel_partner_amount', 15, 2);
            $table->decimal('company_net_amount', 15, 2);
            $table->decimal('annualized_return_percent', 8, 2)->nullable();
            $table->enum('status', ['confirmed', 'paid_out'])->default('confirmed');
            $table->foreignId('settled_by')->constrained('users');
            $table->timestamp('settled_at');
            $table->timestamps();
        });

        Schema::create('settlement_payouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('settlement_id')->constrained('project_settlements')->cascadeOnDelete();
            $table->foreignId('investor_id')->constrained()->cascadeOnDelete();
            $table->decimal('investment_amount', 15, 2);
            $table->decimal('profit_share_amount', 15, 2);
            $table->decimal('total_payout', 15, 2);
            $table->enum('payment_status', ['pending', 'paid'])->default('pending');
            $table->date('paid_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_bank_name')->nullable();
            $table->string('recipient_branch')->nullable();
            $table->string('recipient_account_number')->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamps();
            $table->unique(['settlement_id', 'investor_id']);
        });

        Schema::create('channel_partner_payouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('settlement_id')->constrained('project_settlements')->cascadeOnDelete()->unique();
            $table->foreignId('investor_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->enum('payment_status', ['pending', 'paid'])->default('pending');
            $table->date('paid_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_partner_payouts');
        Schema::dropIfExists('settlement_payouts');
        Schema::dropIfExists('project_settlements');
        Schema::dropIfExists('investor_security_instruments');
        Schema::dropIfExists('project_cost_items');
        Schema::dropIfExists('investments');
        Schema::dropIfExists('investors');
        Schema::dropIfExists('investment_projects');
    }
};
