<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_slides', function (Blueprint $table): void {
            // Null means "legacy/any" — created before slides were tagged to a
            // specific theme/template, so it stays a fallback for every theme
            // until re-tagged. See StorefrontSlide::forCompanyTheme().
            $table->string('theme')->nullable()->after('company_id');
            $table->string('template')->nullable()->after('theme');
        });
    }

    public function down(): void
    {
        Schema::table('storefront_slides', function (Blueprint $table): void {
            $table->dropColumn(['theme', 'template']);
        });
    }
};
