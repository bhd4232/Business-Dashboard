<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ad_proposals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('meta_ad_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            // The top pick from one Generate run; up to 2 alternatives are
            // saved alongside it (is_recommended = false) for transparency.
            $table->boolean('is_recommended')->default(false);
            $table->decimal('suggested_budget', 12, 2);
            // Advisory only — Phase C's create form has no start/stop-time
            // fields, so this is never wired into a real Meta field.
            $table->unsignedSmallInteger('suggested_duration_days')->nullable();
            $table->string('headline')->nullable();
            $table->text('primary_text')->nullable();
            $table->text('description_text')->nullable();
            $table->string('call_to_action')->nullable();
            $table->json('targeting')->nullable();
            $table->text('reasoning_text')->nullable();
            $table->string('ai_provider')->nullable();
            $table->string('ai_model')->nullable();
            // draft -> launched (via CreateMetaAdCampaign) or dismissed.
            $table->string('status', 16)->default('draft');
            $table->foreignId('meta_ad_campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'meta_ad_account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ad_proposals');
    }
};
