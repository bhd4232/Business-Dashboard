<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BEFTN needs a beneficiary's bank routing number, which
     * settlement_payouts never had. channel_partner_payouts never had any
     * recipient/bank fields at all — the 10% channel-partner share had no
     * way to record where it should be sent, only that it was paid — so it
     * gets the same recipient_* columns settlement_payouts already has,
     * plus routing_number.
     */
    public function up(): void
    {
        Schema::table('settlement_payouts', function (Blueprint $table): void {
            $table->string('recipient_routing_number')->nullable()->after('recipient_branch');
        });

        Schema::table('channel_partner_payouts', function (Blueprint $table): void {
            $table->string('recipient_name')->nullable()->after('investor_id');
            $table->string('recipient_bank_name')->nullable()->after('recipient_name');
            $table->string('recipient_branch')->nullable()->after('recipient_bank_name');
            $table->string('recipient_routing_number')->nullable()->after('recipient_branch');
            $table->string('recipient_account_number')->nullable()->after('recipient_routing_number');
        });
    }

    public function down(): void
    {
        Schema::table('settlement_payouts', function (Blueprint $table): void {
            $table->dropColumn('recipient_routing_number');
        });

        Schema::table('channel_partner_payouts', function (Blueprint $table): void {
            $table->dropColumn([
                'recipient_name', 'recipient_bank_name', 'recipient_branch',
                'recipient_routing_number', 'recipient_account_number',
            ]);
        });
    }
};
