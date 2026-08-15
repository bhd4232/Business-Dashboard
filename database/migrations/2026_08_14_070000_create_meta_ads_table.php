<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('meta_ad_set_id')->constrained()->cascadeOnDelete();
            $table->string('meta_id');
            $table->string('name');
            $table->string('status')->nullable();
            $table->string('effective_status')->nullable();
            $table->string('meta_creative_id')->nullable();
            $table->string('headline')->nullable();
            $table->text('primary_text')->nullable();
            $table->text('description_text')->nullable();
            $table->string('call_to_action')->nullable();
            $table->string('destination_url')->nullable();
            $table->string('image_hash')->nullable();
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

            $table->unique(['meta_ad_set_id', 'meta_id']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads');
    }
};
