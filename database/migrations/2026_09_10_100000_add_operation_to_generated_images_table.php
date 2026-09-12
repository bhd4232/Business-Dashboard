<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 11_AI_TOOL_MENU_IMAGE_GENERATION_PLAN.md §11 Phase 4 (remaining) — the kind
 * of operation a GenerateImageJob run performed:
 *
 * - `generate`            — text-to-image (the default, everything so far)
 * - `image_to_image`      — regenerate guided by a reference image + prompt
 * - `background_removal`  — strip the background of a reference image
 *
 * The reference image path for the last two already has a column
 * (`reference_image_path`); this records which operation used it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('generated_images') || Schema::hasColumn('generated_images', 'operation')) {
            return;
        }

        Schema::table('generated_images', function (Blueprint $table): void {
            $table->string('operation')->default('generate')->after('context');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('generated_images', 'operation')) {
            return;
        }

        Schema::table('generated_images', function (Blueprint $table): void {
            $table->dropColumn('operation');
        });
    }
};
