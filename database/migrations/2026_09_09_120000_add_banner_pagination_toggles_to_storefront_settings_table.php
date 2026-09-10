<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A demo/restore database may already carry the columns even when its
        // migrations ledger was not copied with the schema. Treat that
        // recoverable drift as already applied instead of blocking every
        // later migration with a duplicate-column error.
        if (Schema::hasColumn('storefront_settings', 'banner_pagination_desktop')) {
            return;
        }

        Schema::table('storefront_settings', function (Blueprint $table): void {
            // Hero banner dot navigation, toggled per display type from
            // Storefront -> Hero Slides. Both default true so the pagination
            // keeps showing exactly as before until an owner turns it off for
            // a given display. "Mobile" is every width below the banner's own
            // 1024px desktop breakpoint. See image-banner.blade.php and the
            // `.storefront-image-banner-hide-nav-*` rules in resources/css/app.css.
            $table->boolean('banner_pagination_desktop')->default(true)->after('marketplace_business_strip_enabled');
            $table->boolean('banner_pagination_mobile')->default(true)->after('banner_pagination_desktop');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('storefront_settings', 'banner_pagination_desktop')) {
            return;
        }

        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropColumn(['banner_pagination_desktop', 'banner_pagination_mobile']);
        });
    }
};
