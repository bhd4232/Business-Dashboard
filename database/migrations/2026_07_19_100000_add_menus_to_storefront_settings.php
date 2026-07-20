<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->json('header_menu')->nullable();
            $table->json('footer_menu')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropColumn(['header_menu', 'footer_menu']);
        });
    }
};
