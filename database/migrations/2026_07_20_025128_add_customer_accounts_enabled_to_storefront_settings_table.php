<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table) {
            $table->boolean('customer_accounts_enabled')->default(true)->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table) {
            $table->dropColumn('customer_accounts_enabled');
        });
    }
};
