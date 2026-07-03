<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->string('hero_heading')->nullable()->after('whatsapp_number');
            $table->text('hero_subheading')->nullable()->after('hero_heading');
            $table->string('hero_cta_label')->nullable()->after('hero_subheading');
            $table->string('theme_mode')->default('system')->after('hero_cta_label');
        });
    }

    public function down(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropColumn(['hero_heading', 'hero_subheading', 'hero_cta_label', 'theme_mode']);
        });
    }
};
