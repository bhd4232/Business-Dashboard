<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // single | combo
            $table->string('title');
            $table->string('slug');
            $table->string('status')->default('draft'); // draft | published | archived
            $table->text('short_description')->nullable();

            // Pricing
            $table->string('price_mode')->default('auto_sum'); // auto_sum | manual
            $table->decimal('manual_price', 12, 2)->nullable();
            $table->string('discount_type')->nullable(); // percent | flat
            $table->decimal('discount_value', 12, 2)->nullable();

            // Landing page content (block-based)
            $table->json('blocks')->nullable();
            $table->boolean('is_ai_generated')->default(false);
            $table->timestamp('ai_generated_at')->nullable();

            // SEO / meta
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('cover_image')->nullable();

            $table->boolean('online_payment_required')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
