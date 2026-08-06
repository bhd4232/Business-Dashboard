<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->string('typography_heading_tracking')->default('tight')->after('typography_line_height');
            $table->string('typography_body_tracking')->default('normal')->after('typography_heading_tracking');
            $table->string('typography_content_width')->default('comfortable')->after('typography_body_tracking');
            $table->string('appearance_preset')->default('modern')->after('typography_content_width');
            $table->string('appearance_container_width')->default('wide')->after('appearance_preset');
            $table->string('appearance_page_gutter')->default('comfortable')->after('appearance_container_width');
            $table->string('appearance_corner_radius')->default('soft')->after('appearance_page_gutter');
            $table->string('appearance_shadow')->default('subtle')->after('appearance_corner_radius');
            $table->string('appearance_motion')->default('standard')->after('appearance_shadow');
            $table->string('appearance_card_hover')->default('subtle')->after('appearance_motion');
        });

        DB::table('storefront_settings')
            ->where('typography_base_size', 14)
            ->update(['typography_base_size' => 16]);
    }

    public function down(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'typography_heading_tracking',
                'typography_body_tracking',
                'typography_content_width',
                'appearance_preset',
                'appearance_container_width',
                'appearance_page_gutter',
                'appearance_corner_radius',
                'appearance_shadow',
                'appearance_motion',
                'appearance_card_hover',
            ]);
        });
    }
};
