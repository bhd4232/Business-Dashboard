<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v3 gap analysis P2.4 — deed clauses 5-6 / channel-partner agreement clause 8:
 * at every settlement cycle an investor either withdraws their payout or rolls
 * it (fully or partially) into the next project, and exiting the relationship
 * entirely requires 60 days' written notice. Both tables are pure
 * record-keeping / tracking (no hard block elsewhere) — matching the "soft"
 * approach already used for the investment window (P2.3, Q4).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('investor_cycle_elections')) {
            Schema::create('investor_cycle_elections', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('settlement_payout_id')->constrained()->cascadeOnDelete();
                $table->string('election', 20); // withdraw | reinvest | partial_reinvest
                $table->decimal('reinvest_amount', 15, 2)->nullable();
                $table->foreignId('next_project_id')->nullable()->constrained('investment_projects')->nullOnDelete();
                $table->foreignId('reinvestment_id')->nullable()->constrained('investments')->nullOnDelete();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique('settlement_payout_id');
            });
        }

        if (! Schema::hasTable('investment_withdrawal_notices')) {
            Schema::create('investment_withdrawal_notices', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('investor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('project_id')->nullable()->constrained('investment_projects')->nullOnDelete();
                $table->date('notice_given_at');
                $table->date('effective_at');
                $table->text('reason')->nullable();
                $table->string('status', 20)->default('pending'); // pending | honored | cancelled
                $table->timestamps();
                $table->index(['company_id', 'investor_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_withdrawal_notices');
        Schema::dropIfExists('investor_cycle_elections');
    }
};
