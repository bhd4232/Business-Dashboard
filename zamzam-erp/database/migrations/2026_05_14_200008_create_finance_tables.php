<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Credit Ledger ─────────────────────────────────
        Schema::create('credit_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->enum('type', ['debit', 'credit']); // debit=charge, credit=payment
            $table->decimal('amount_bdt', 14, 2);
            $table->decimal('balance_after_bdt', 14, 2);
            $table->string('reference_type', 100)->nullable(); // morphable
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->date('date');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['customer_id', 'date'], 'idx_credit_ledger_customer_date');
        });

        // ─── Payments ──────────────────────────────────────
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_no', 50)->unique();
            $table->string('payer_type', 50); // customer or supplier
            $table->unsignedBigInteger('payer_id');
            $table->enum('direction', ['received', 'paid'])->default('received');
            $table->decimal('amount_bdt', 14, 2);
            $table->enum('method', ['cash', 'bank_transfer', 'check', 'mobile_banking', 'online'])->default('cash');
            $table->string('reference_no', 100)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->string('attachment', 500)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['payer_id', 'method'], 'idx_payments_payer_method');
        });

        // ─── Payment Allocations ────────────────────────────
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained();
            $table->decimal('amount_bdt', 14, 2);
            $table->timestamps();
        });

        // ─── Supplier Payments ─────────────────────────────
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_no', 50)->unique();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount_cny', 14, 2)->nullable();
            $table->decimal('amount_usd', 14, 2)->nullable();
            $table->decimal('amount_bdt', 14, 2);
            $table->decimal('exchange_rate', 12, 6)->default(1);
            $table->enum('method', ['bank_transfer', 'tt', 'alipay', 'wechat_pay', 'other'])->default('bank_transfer');
            $table->string('reference_no', 100)->nullable();
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->string('attachment', 500)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // ─── Credit Adjustments ────────────────────────────
        Schema::create('credit_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->enum('type', ['increase', 'decrease', 'write_off']);
            $table->decimal('amount_bdt', 14, 2);
            $table->string('reason', 255);
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // ─── Chart of Accounts ─────────────────────────────
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->enum('type', ['asset', 'liability', 'equity', 'revenue', 'expense']);
            $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // ─── Journals ──────────────────────────────────────
        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->string('journal_no', 50)->unique();
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->date('date');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // ─── Journal Entries ───────────────────────────────
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts');
            $table->enum('type', ['debit', 'credit']);
            $table->decimal('amount_bdt', 14, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('account_id', 'idx_journal_entries_account');
        });

        // ─── Expense Categories ────────────────────────────
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->foreignId('account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─── Expenses ──────────────────────────────────────
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_no', 50)->unique();
            $table->foreignId('category_id')->constrained('expense_categories');
            $table->decimal('amount_bdt', 14, 2);
            $table->date('expense_date');
            $table->string('description')->nullable();
            $table->string('attachment', 500)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('journals');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('credit_adjustments');
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('credit_ledger');
    }
};
