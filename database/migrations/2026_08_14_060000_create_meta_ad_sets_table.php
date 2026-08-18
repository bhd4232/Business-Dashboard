<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ad_sets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('meta_ad_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('meta_id');
            $table->string('name');
            $table->string('status')->nullable();
            $table->string('effective_status')->nullable();
            $table->string('optimization_goal')->nullable();
            $table->string('billing_event')->nullable();
            $table->decimal('bid_amount', 12, 2)->nullable();
            $table->decimal('daily_budget', 12, 2)->nullable();
            $table->decimal('lifetime_budget', 12, 2)->nullable();
            $table->json('targeting')->nullable();
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            // Only set when created via this app (Phase C/D) so we know which
            // product an ad set is promoting — nullable for synced-from-Meta rows.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('spend', 12, 2)->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->decimal('ctr', 8, 4)->default(0);
            $table->decimal('cpc', 12, 4)->default(0);
            $table->unsignedBigInteger('reach')->default(0);
            $table->json('results')->nullable();
            $table->timestamp('insights_synced_at')->nullable();
            $table->string('source', 16)->default('meta');
            $table->timestamps();

            $table->unique(['meta_ad_campaign_id', 'meta_id']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ad_sets');
    }
};
