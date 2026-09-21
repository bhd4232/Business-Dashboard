<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where a reseller's commission should be paid -- their own choice
     * (owner: "আমি mfs এ অথবা ব্যাংক একাউন্টে" pays them). `reseller_payout_details`
     * is encrypted:array, same pattern as StorefrontSetting::payment_credentials --
     * {bank_name, branch, routing_number, account_number, account_name} for
     * 'bank', {msisdn} for an mfs_* method.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->enum('reseller_payout_method', ['bank', 'mfs_bkash', 'mfs_nagad', 'mfs_rocket'])->nullable()->after('reseller_slug');
            $table->text('reseller_payout_details')->nullable()->after('reseller_payout_method');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn(['reseller_payout_method', 'reseller_payout_details']);
        });
    }
};
