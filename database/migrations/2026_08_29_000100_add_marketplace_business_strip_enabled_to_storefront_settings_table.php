<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            // Dedicated toggle for the hero homepage's "Built for repeat and
            // wholesale buyers" bottom banner. Defaults off so the banner is
            // hidden until an owner opts in from Storefront Settings.
            $table->boolean('marketplace_business_strip_enabled')
                ->default(false)
                ->after('marketplace_business_accounts_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropColumn('marketplace_business_strip_enabled');
        });
    }
};
