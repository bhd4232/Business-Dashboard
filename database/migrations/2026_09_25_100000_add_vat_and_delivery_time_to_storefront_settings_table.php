<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            // VAT is off by default so existing storefront totals never change
            // until the owner turns it on and enters the real rate.
            $table->boolean('vat_enabled')->default(false)->after('delivery_additional_per_kg');
            $table->decimal('vat_rate', 5, 2)->default(0)->after('vat_enabled');
            $table->string('vat_mode', 16)->default('exclusive')->after('vat_rate');
            $table->string('vat_label', 40)->nullable()->after('vat_mode');
            // When on, a product's own ERP VAT rate (Products → VAT rate)
            // wins over the store-wide rate for that line.
            $table->boolean('vat_use_product_rate')->default(false)->after('vat_label');
            // Free-text delivery time per delivery area (e.g. "1–2 days");
            // empty means the storefront shows no delivery-time promise.
            $table->string('delivery_time_inside', 120)->nullable()->after('vat_use_product_rate');
            $table->string('delivery_time_outside', 120)->nullable()->after('delivery_time_inside');
        });
    }

    public function down(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'vat_enabled',
                'vat_rate',
                'vat_mode',
                'vat_label',
                'vat_use_product_rate',
                'delivery_time_inside',
                'delivery_time_outside',
            ]);
        });
    }
};
