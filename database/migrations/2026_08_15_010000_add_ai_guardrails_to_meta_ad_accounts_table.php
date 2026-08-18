<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_ad_accounts', function (Blueprint $table): void {
            // Phase D: the AI Marketing Assistant is off for an account until
            // ai_daily_budget_max is set — no invented default spending ceiling.
            $table->decimal('ai_daily_budget_min', 12, 2)->nullable()->after('exchange_rate_to_company_currency');
            $table->decimal('ai_daily_budget_max', 12, 2)->nullable()->after('ai_daily_budget_min');
            $table->unsignedSmallInteger('ai_max_duration_days')->nullable()->default(30)->after('ai_daily_budget_max');
        });
    }

    public function down(): void
    {
        Schema::table('meta_ad_accounts', function (Blueprint $table): void {
            $table->dropColumn(['ai_daily_budget_min', 'ai_daily_budget_max', 'ai_max_duration_days']);
        });
    }
};
