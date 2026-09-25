<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            // Marketplace Pro homepage copy that used to be hardcoded in the
            // theme view. Every value is owner-entered; an empty value hides
            // the element instead of falling back to invented text.
            $table->json('marketplace_trust_items')->nullable()->after('marketplace_campaign_cta_label');
            $table->json('marketplace_bulk_pricing_rows')->nullable()->after('marketplace_trust_items');
            $table->string('marketplace_business_heading', 140)->nullable()->after('marketplace_bulk_pricing_rows');
            $table->text('marketplace_business_text')->nullable()->after('marketplace_business_heading');
            $table->string('marketplace_helpline', 40)->nullable()->after('marketplace_business_text');
            // New, off-by-default utility bar. The legacy
            // marketplace_announcement_* columns default to enabled and
            // may hold text the owner chose to hide, so they stay unused.
            $table->boolean('marketplace_utility_bar_enabled')->default(false)->after('marketplace_helpline');
            $table->string('marketplace_utility_bar_text', 160)->nullable()->after('marketplace_utility_bar_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'marketplace_trust_items',
                'marketplace_bulk_pricing_rows',
                'marketplace_business_heading',
                'marketplace_business_text',
                'marketplace_helpline',
                'marketplace_utility_bar_enabled',
                'marketplace_utility_bar_text',
            ]);
        });
    }
};
