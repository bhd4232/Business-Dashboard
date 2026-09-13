<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v3 gap analysis P1.3 + P1.4.
 *
 * P1.4 — a settlement now starts as `draft` (payout schedule computed but not
 * yet locked), is promoted to `confirmed` by a Super Admin, and can be voided
 * back to a re-openable project while no payout has been paid. The original
 * enum('confirmed','paid_out') can't hold `draft`, so — same fix as
 * 2026_07_17 did for orders — the column becomes a plain string, valid values
 * enforced in code (ProjectSettlement::STATUSES).
 *
 * P1.3 — a losing project can now be settled. `outcome` records who bears the
 * loss per the contract (deed clause 4 / channel-partner agreement clause 4):
 * investors (capital owners) for force-majeure losses, the company for its
 * own negligence or breach.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_settlements', function (Blueprint $table): void {
            $table->string('status', 20)->default('draft')->change();
        });

        Schema::table('project_settlements', function (Blueprint $table): void {
            if (! Schema::hasColumn('project_settlements', 'outcome')) {
                $table->string('outcome', 30)->default('profit')->after('status');
            }
            if (! Schema::hasColumn('project_settlements', 'loss_reason')) {
                $table->text('loss_reason')->nullable()->after('outcome');
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_settlements', function (Blueprint $table): void {
            $table->dropColumn(['outcome', 'loss_reason']);
        });
        // status intentionally left as a string — reverting to the old enum
        // would reject any `draft` rows that now legitimately exist.
    }
};
