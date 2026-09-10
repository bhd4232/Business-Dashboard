<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 11_AI_TOOL_MENU_IMAGE_GENERATION_PLAN.md §11 Phase 4 — the generation
 * library's "favourites". A starred generation doubles as a reusable prompt
 * template (its prompt can be re-run from the library), so no separate
 * templates table is needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('generated_images') || Schema::hasColumn('generated_images', 'is_favorite')) {
            return;
        }

        Schema::table('generated_images', function (Blueprint $table): void {
            $table->boolean('is_favorite')->default(false)->after('is_video_reference');
            $table->index(['company_id', 'is_favorite']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('generated_images', 'is_favorite')) {
            return;
        }

        Schema::table('generated_images', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'is_favorite']);
            $table->dropColumn('is_favorite');
        });
    }
};
