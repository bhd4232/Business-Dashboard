<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI Expense Scan — photograph a handwritten note / receipt / any expense
 * list, an AI vision model reads it into draft lines, the owner reviews and
 * corrects them, then publishes. Drafts live here (never in `expenses`),
 * because an `Expense` row posts to the ledger the moment it is saved.
 *
 * - `expense_scans`: one upload (its private images, status, raw AI reply).
 * - `expense_scan_items`: one draft line each; `expense_id` is set once the
 *   line has been published into a real Expense.
 * - `expenses.expense_scan_id`: links a published Expense back to the scan
 *   whose photo is its proof.
 *
 * Both new tables are company-owned (`BelongsToCompany` + `CompanyScope`,
 * registered in `MultiCompanyIsolationTest`).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('expense_scans')) {
            Schema::create('expense_scans', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('default_account_id')->nullable()->constrained('accounts')->nullOnDelete();

                $table->json('image_paths');
                $table->string('status')->default('pending');
                $table->text('error_message')->nullable();

                $table->string('api_format')->nullable();
                $table->string('model')->nullable();
                $table->json('ai_response')->nullable();

                $table->timestamp('processed_at')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['company_id', 'status']);
            });
        }

        if (! Schema::hasTable('expense_scan_items')) {
            Schema::create('expense_scan_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('expense_scan_id')->constrained()->cascadeOnDelete();

                $table->date('expense_date')->nullable();
                $table->string('description')->nullable();
                $table->decimal('amount', 12, 2)->nullable();
                $table->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();
                $table->string('new_category_name')->nullable();
                $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
                $table->string('reference')->nullable();
                $table->text('note')->nullable();
                $table->decimal('confidence', 3, 2)->nullable();
                $table->unsignedInteger('sort_order')->default(0);

                $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();

                $table->index(['expense_scan_id', 'sort_order']);
            });
        }

        if (! Schema::hasColumn('expenses', 'expense_scan_id')) {
            Schema::table('expenses', function (Blueprint $table): void {
                $table->foreignId('expense_scan_id')->nullable()->after('note')->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('expenses', 'expense_scan_id')) {
            Schema::table('expenses', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('expense_scan_id');
            });
        }

        Schema::dropIfExists('expense_scan_items');
        Schema::dropIfExists('expense_scans');
    }
};
