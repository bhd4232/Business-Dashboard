<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->string('theme_palette_preset')->default('custom')->after('theme_foreground_mode');
            $table->string('theme_secondary_color')->default('#0F172A')->after('theme_palette_preset');
            $table->string('theme_accent_color')->default('#F59E0B')->after('theme_secondary_color');
            $table->string('theme_background_color')->default('#FFFFFF')->after('theme_accent_color');
            $table->string('theme_surface_color')->default('#FFFFFF')->after('theme_background_color');
            $table->string('theme_text_color')->default('#111827')->after('theme_surface_color');
            $table->string('theme_muted_text_color')->default('#6B7280')->after('theme_text_color');
            $table->string('theme_border_color')->default('#E5E7EB')->after('theme_muted_text_color');
            $table->string('theme_dark_background_color')->default('#030712')->after('theme_border_color');
            $table->string('theme_dark_surface_color')->default('#111827')->after('theme_dark_background_color');
            $table->string('theme_dark_text_color')->default('#F3F4F6')->after('theme_dark_surface_color');
            $table->string('theme_dark_muted_text_color')->default('#9CA3AF')->after('theme_dark_text_color');
            $table->string('theme_dark_border_color')->default('#374151')->after('theme_dark_muted_text_color');
            $table->string('typography_preset')->default('system')->after('theme_dark_border_color');
            $table->string('typography_heading_font')->default('system')->after('typography_preset');
            $table->string('typography_body_font')->default('system')->after('typography_heading_font');
            $table->unsignedTinyInteger('typography_base_size')->default(14)->after('typography_body_font');
            $table->unsignedSmallInteger('typography_heading_weight')->default(700)->after('typography_base_size');
            $table->unsignedSmallInteger('typography_body_weight')->default(400)->after('typography_heading_weight');
            $table->string('typography_scale')->default('balanced')->after('typography_body_weight');
            $table->string('typography_line_height')->default('normal')->after('typography_scale');
        });
    }

    public function down(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'theme_palette_preset',
                'theme_secondary_color',
                'theme_accent_color',
                'theme_background_color',
                'theme_surface_color',
                'theme_text_color',
                'theme_muted_text_color',
                'theme_border_color',
                'theme_dark_background_color',
                'theme_dark_surface_color',
                'theme_dark_text_color',
                'theme_dark_muted_text_color',
                'theme_dark_border_color',
                'typography_preset',
                'typography_heading_font',
                'typography_body_font',
                'typography_base_size',
                'typography_heading_weight',
                'typography_body_weight',
                'typography_scale',
                'typography_line_height',
            ]);
        });
    }
};
