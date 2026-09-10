<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 11_AI_TOOL_MENU_IMAGE_GENERATION_PLAN.md §6.2 — one row per Image
 * Generation request. Holds the generation metadata (prompt, enhancement
 * trail, provider profile used, status) that the Media Hub row for each
 * finished output does not carry. Company-owned: `BelongsToCompany` +
 * `CompanyScope`, registered in `MultiCompanyIsolationTest`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('generated_images')) {
            return;
        }

        Schema::create('generated_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('tool')->default('image_generation');
            $table->string('context')->default('general');

            $table->text('original_prompt')->nullable();
            $table->text('prompt');

            $table->string('provider_profile_id')->nullable();
            $table->string('provider_label')->nullable();
            $table->string('api_format')->nullable();
            $table->string('model')->nullable();

            $table->string('reference_image_path')->nullable();
            $table->string('aspect_ratio')->nullable();
            $table->unsignedInteger('variations_requested')->default(1);

            $table->json('output_paths')->nullable();

            $table->nullableMorphs('linked');
            $table->boolean('is_video_reference')->default(false);

            $table->string('status')->default('queued');
            $table->text('error_message')->nullable();
            $table->json('provider_response')->nullable();

            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'context']);
            $table->index(['company_id', 'is_video_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_images');
    }
};
