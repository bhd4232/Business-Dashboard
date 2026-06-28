<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('theme_color')->default('#0F766E');
            $table->string('logo')->nullable();
            $table->json('banner_images')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_settings');
    }
};
