<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ad_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('meta_ad_account_id')->constrained()->cascadeOnDelete();
            $table->string('meta_id');
            $table->string('name');
            $table->string('objective')->nullable();
            $table->string('status')->nullable();
            $table->string('effective_status')->nullable();
            $table->decimal('daily_budget', 12, 2)->nullable();
            $table->decimal('lifetime_budget', 12, 2)->nullable();
            $table->string('buying_type')->nullable();
            $table->json('special_ad_categories')->nullable();
            $table->timestamp('start_time')->nullable();
            $table->timestamp('stop_time')->nullable();
            $table->decimal('spend', 12, 2)->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->decimal('ctr', 8, 4)->default(0);
            $table->decimal('cpc', 12, 4)->default(0);
            $table->unsignedBigInteger('reach')->default(0);
            $table->json('results')->nullable();
            $table->timestamp('insights_synced_at')->nullable();
            // 'meta' = pulled from an account that already had this campaign;
            // 'erp' = created from this app (Phase C).
            $table->string('source', 16)->default('meta');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['meta_ad_account_id', 'meta_id']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ad_campaigns');
    }
};
