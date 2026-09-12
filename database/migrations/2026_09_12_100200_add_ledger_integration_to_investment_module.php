<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v3 gap analysis P2.5 (Q5 — owner asked for a recommendation, locked in
 * 01_INVESTOR_MODULE_v3_GAP_ANALYSIS.md): investor capital-in and payout-out
 * were invisible in the books. Builds on the existing Voucher + FundSource
 * system rather than a new ledger engine — no separate liability account
 * (owner confirmed); auto-created vouchers stay `pending` for someone to
 * verify/approve through the existing workflow. Both fund-source columns are
 * nullable and admin-configurable per project: a project that never sets
 * them creates no vouchers at all (opt-in, same philosophy as the
 * investment window in P2.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        // `transaction_type` was a native enum -- same fix `2026_07_17` used
        // for orders.status and this module's own 2026_09_07_100300 used for
        // project_settlements.status, since a DB-level CHECK constraint can't
        // admit the new `investor_payout` value otherwise. Valid values stay
        // enforced in code (Voucher::TRANSACTION_TYPES).
        Schema::table('vouchers', function (Blueprint $table): void {
            $table->string('transaction_type', 30)->change();
        });

        Schema::table('investment_projects', function (Blueprint $table): void {
            if (! Schema::hasColumn('investment_projects', 'receiving_fund_source_id')) {
                $table->foreignId('receiving_fund_source_id')->nullable()->after('company_contribution_note')->constrained('fund_sources')->nullOnDelete();
            }
            if (! Schema::hasColumn('investment_projects', 'payout_fund_source_id')) {
                $table->foreignId('payout_fund_source_id')->nullable()->after('receiving_fund_source_id')->constrained('fund_sources')->nullOnDelete();
            }
        });

        Schema::table('investments', function (Blueprint $table): void {
            if (! Schema::hasColumn('investments', 'voucher_id')) {
                $table->foreignId('voucher_id')->nullable()->after('override_reason')->constrained('vouchers')->nullOnDelete();
            }
        });

        Schema::table('settlement_payouts', function (Blueprint $table): void {
            if (! Schema::hasColumn('settlement_payouts', 'voucher_id')) {
                $table->foreignId('voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            }
        });

        Schema::table('channel_partner_payouts', function (Blueprint $table): void {
            if (! Schema::hasColumn('channel_partner_payouts', 'voucher_id')) {
                $table->foreignId('voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('investment_projects', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('receiving_fund_source_id');
            $table->dropConstrainedForeignId('payout_fund_source_id');
        });
        Schema::table('investments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('voucher_id');
        });
        Schema::table('settlement_payouts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('voucher_id');
        });
        Schema::table('channel_partner_payouts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('voucher_id');
        });
    }
};
