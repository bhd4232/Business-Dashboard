<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 11_AI_TOOL_MENU_IMAGE_GENERATION_PLAN.md §11 Phase 5 — governance:
 *
 * - `estimated_cost`  — GenerateImageJob stamps this from the provider
 *   profile's admin-set "approx. cost per image" × images produced, so the
 *   usage dashboard can total spend by user / provider without calling any
 *   billing API.
 * - `review_status` + `reviewed_by` / `reviewed_at` — the optional approval
 *   workflow. A generation made by a role the company flags for review lands
 *   `pending` and cannot be attached to a record until a reviewer approves
 *   it. Everything else is `not_required`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('generated_images')) {
            return;
        }

        Schema::table('generated_images', function (Blueprint $table): void {
            if (! Schema::hasColumn('generated_images', 'estimated_cost')) {
                $table->decimal('estimated_cost', 12, 4)->default(0)->after('is_favorite');
            }

            if (! Schema::hasColumn('generated_images', 'review_status')) {
                $table->string('review_status')->default('not_required')->after('estimated_cost');
                $table->foreignId('reviewed_by')->nullable()->after('review_status')->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
                $table->text('review_note')->nullable()->after('reviewed_at');
                $table->index(['company_id', 'review_status']);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('generated_images', 'review_status')) {
            return;
        }

        Schema::table('generated_images', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'review_status']);
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['estimated_cost', 'review_status', 'reviewed_at', 'review_note']);
        });
    }
};
