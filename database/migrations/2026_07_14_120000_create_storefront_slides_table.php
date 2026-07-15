<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_slides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('image');
            $table->string('image_mobile')->nullable();
            $table->string('heading')->nullable();
            $table->string('subheading')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'is_active', 'sort_order']);
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->string('image')->nullable()->after('description');
        });

        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->string('trust_strip_delivery')->nullable()->after('hero_cta_label');
            $table->string('trust_strip_return')->nullable()->after('trust_strip_delivery');
            $table->string('trust_strip_payment')->nullable()->after('trust_strip_return');
        });
    }

    public function down(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropColumn(['trust_strip_delivery', 'trust_strip_return', 'trust_strip_payment']);
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropColumn('image');
        });

        Schema::dropIfExists('storefront_slides');
    }
};
