<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->string('woocommerce_base_url')->nullable()->after('theme_mode');
            $table->text('woocommerce_credentials')->nullable()->after('woocommerce_base_url');
        });
    }

    public function down(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropColumn(['woocommerce_base_url', 'woocommerce_credentials']);
        });
    }
};
