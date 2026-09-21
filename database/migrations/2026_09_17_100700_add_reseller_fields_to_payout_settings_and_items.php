<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_payout_settings', function (Blueprint $table): void {
            // Owner-confirmed default (2026-09-17): 3 days after delivery.
            // Configurable per company, not hardcoded in the service.
            $table->unsignedInteger('reseller_commission_hold_days')->default(3);
        });

        Schema::table('payout_items', function (Blueprint $table): void {
            $table->string('recipient_mfs_number')->nullable()->after('recipient_account_number');
        });
    }

    public function down(): void
    {
        Schema::table('company_payout_settings', function (Blueprint $table): void {
            $table->dropColumn('reseller_commission_hold_days');
        });

        Schema::table('payout_items', function (Blueprint $table): void {
            $table->dropColumn('recipient_mfs_number');
        });
    }
};
