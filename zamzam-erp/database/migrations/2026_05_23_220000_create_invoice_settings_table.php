<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_settings', function (Blueprint $table) {
            $table->id();

            // ── Company / Branding ──────────────────────────────────────────
            $table->string('company_name')->default('Zamzam International');
            $table->string('company_tagline')->nullable();
            $table->string('address')->nullable()->default('House-59, Road-6/A, Sector-5, Uttara, Dhaka');
            $table->string('hotline_1')->nullable()->default('01811754232');
            $table->string('hotline_2')->nullable()->default('01894449445');
            $table->string('hotline_3')->nullable()->default('01678413888');
            $table->string('email')->nullable()->default('zamzamgadgetsbd@gmail.com');
            $table->string('website')->nullable()->default('zamzamint.com');
            $table->string('facebook')->nullable()->default('facebook.com/zamzamintl');

            // ── Invoice Configuration ───────────────────────────────────────
            $table->string('invoice_prefix')->default('INV');
            $table->unsignedTinyInteger('default_payment_terms_days')->nullable();
            $table->text('default_notes')->nullable();
            $table->string('thank_you_message')->default('Thank You For Purchasing From Us.');

            // ── Print / PDF Display Options ─────────────────────────────────
            $table->boolean('show_product_images')->default(true);
            $table->boolean('show_product_weight')->default(true);
            $table->boolean('show_delivery_partner')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_settings');
    }
};
