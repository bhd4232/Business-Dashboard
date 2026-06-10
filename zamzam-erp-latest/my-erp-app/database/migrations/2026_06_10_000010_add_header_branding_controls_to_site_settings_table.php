<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->boolean('header_show_site_name')->default(false)->after('favicon');
            $table->boolean('header_show_tagline')->default(false)->after('header_show_site_name');
            $table->unsignedSmallInteger('header_logo_width')->default(180)->after('header_show_tagline');
            $table->unsignedSmallInteger('header_logo_height')->default(64)->after('header_logo_width');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'header_show_site_name',
                'header_show_tagline',
                'header_logo_width',
                'header_logo_height',
            ]);
        });
    }
};
