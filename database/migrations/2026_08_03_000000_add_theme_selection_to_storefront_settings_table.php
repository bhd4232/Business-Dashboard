<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->string('storefront_theme')->default('builtin')->after('company_id');
            $table->string('homepage_template')->default('default')->after('storefront_theme');
            $table->boolean('marketplace_announcement_enabled')->default(true)->after('homepage_template');
            $table->string('marketplace_announcement_text', 160)->nullable()->after('marketplace_announcement_enabled');
            $table->boolean('marketplace_quote_enabled')->default(true)->after('marketplace_announcement_text');
            $table->boolean('marketplace_business_accounts_enabled')->default(true)->after('marketplace_quote_enabled');
            $table->boolean('marketplace_trust_strip_enabled')->default(true)->after('marketplace_business_accounts_enabled');
            $table->boolean('marketplace_deals_enabled')->default(true)->after('marketplace_trust_strip_enabled');
            $table->boolean('marketplace_categories_enabled')->default(true)->after('marketplace_deals_enabled');
            $table->boolean('marketplace_bulk_pricing_enabled')->default(true)->after('marketplace_categories_enabled');
            $table->boolean('marketplace_sidebar_enabled')->default(true)->after('marketplace_bulk_pricing_enabled');
            $table->unsignedSmallInteger('marketplace_product_limit')->default(10)->after('marketplace_sidebar_enabled');
            $table->string('marketplace_campaign_badge', 60)->nullable()->after('marketplace_product_limit');
            $table->string('marketplace_campaign_heading', 140)->nullable()->after('marketplace_campaign_badge');
            $table->text('marketplace_campaign_subheading')->nullable()->after('marketplace_campaign_heading');
            $table->string('marketplace_campaign_cta_label', 40)->nullable()->after('marketplace_campaign_subheading');
        });
    }

    public function down(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'storefront_theme',
                'homepage_template',
                'marketplace_announcement_enabled',
                'marketplace_announcement_text',
                'marketplace_quote_enabled',
                'marketplace_business_accounts_enabled',
                'marketplace_trust_strip_enabled',
                'marketplace_deals_enabled',
                'marketplace_categories_enabled',
                'marketplace_bulk_pricing_enabled',
                'marketplace_sidebar_enabled',
                'marketplace_product_limit',
                'marketplace_campaign_badge',
                'marketplace_campaign_heading',
                'marketplace_campaign_subheading',
                'marketplace_campaign_cta_label',
            ]);
        });
    }
};
