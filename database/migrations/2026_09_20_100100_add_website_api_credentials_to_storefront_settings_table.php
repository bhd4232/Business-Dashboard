<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A demo/restore database may already contain the column even when
        // its migrations ledger was not copied with the schema. Treat that
        // recoverable drift as already applied instead of blocking every
        // later migration with a duplicate-column error.
        if (Schema::hasColumn('storefront_settings', 'website_api_credentials')) {
            return;
        }

        Schema::table('storefront_settings', function (Blueprint $table): void {
            // Outbound webhook config for the external-website API
            // integration (webhook_url, webhook_secret, is_enabled) —
            // encrypted like every other third-party credential column on
            // this table (woocommerce_credentials, payment_credentials, ...).
            // The inbound API key itself lives on company_api_keys, not here.
            $table->text('website_api_credentials')->nullable()->after('woocommerce_credentials');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('storefront_settings', 'website_api_credentials')) {
            return;
        }

        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropColumn('website_api_credentials');
        });
    }
};
