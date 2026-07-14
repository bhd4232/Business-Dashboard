<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', [
                'cash', 'bank', 'mobile_banking', 'wallet', 'petty_cash',
                'owner_investment', 'partner_investment', 'business_profit',
                'bank_loan', 'customer_advance', 'supplier_credit', 'other',
            ]);
            // Wraps a real Account for cash/bank/mobile_banking/petty_cash/wallet
            // types so balance is always read from the existing accounts +
            // transaction_ledgers system, never duplicated here.
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'type']);
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('voucher_number')->unique();
            $table->enum('type', ['credit', 'debit']);
            $table->enum('status', ['pending', 'verified', 'approved', 'rejected', 'cancelled'])
                ->default('pending');
            $table->enum('transaction_type', [
                'inventory_purchase', 'business_expense', 'capital_investment',
                'owner_withdrawal', 'supplier_payment', 'customer_payment',
                'loan', 'refund', 'asset_purchase', 'fund_transfer', 'other',
            ]);
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('BDT');

            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('fund_source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();

            $table->string('payment_method')->nullable();
            $table->string('transaction_id')->nullable();
            $table->enum('confirmation_source', [
                'telegram', 'whatsapp', 'messenger', 'sms', 'phone_call', 'email', 'manual',
            ])->nullable();

            $table->text('purpose')->nullable();
            $table->text('remarks')->nullable();

            $table->foreignId('submitted_by')->constrained('users');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // Reference to the downstream record created on approval (a
            // CustomerPayment/Expense/SupplierPayment, or later a Mudarabah
            // investment) — a lightweight morph-style pointer.
            $table->string('resulting_model_type')->nullable();
            $table->unsignedBigInteger('resulting_model_id')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'type', 'status']);
            $table->index('transaction_type');
        });

        Schema::create('voucher_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_type')->nullable();
            $table->string('label')->nullable();
            $table->timestamps();
        });

        Schema::create('fund_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('transfer_number')->unique();
            $table->foreignId('from_account_id')->constrained('accounts');
            $table->foreignId('to_account_id')->constrained('accounts');
            $table->decimal('amount', 15, 2);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('remarks')->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::table('purchases', function (Blueprint $table) {
            // [{"fund_source_id": 1, "amount": 200000}, ...] — supports
            // splitting one purchase across multiple funding sources.
            $table->json('funding_sources')->nullable()->after('custom_costs');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('funding_sources');
        });

        Schema::dropIfExists('fund_transfers');
        Schema::dropIfExists('voucher_attachments');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('fund_sources');
    }
};
