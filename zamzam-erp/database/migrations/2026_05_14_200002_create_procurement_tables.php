<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Suppliers ─────────────────────────────────────
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name_chinese');
            $table->string('name_english');
            $table->string('company_name')->nullable();
            $table->string('wechat_id', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('country', 2)->default('CN');
            $table->string('website', 500)->nullable();
            $table->tinyInteger('rating')->nullable();
            $table->string('payment_terms')->nullable();
            $table->string('preferred_currency', 3)->default('CNY');
            $table->json('bank_details')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // ─── Supplier Contacts ─────────────────────────────
        Schema::create('supplier_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('designation', 100)->nullable();
            $table->string('wechat_id', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        // ─── Categories ───────────────────────────────────
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // ─── Products ──────────────────────────────────────
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 100)->unique();
            $table->string('name');
            $table->string('name_chinese')->nullable();
            $table->foreignId('category_id')->constrained('categories');
            $table->string('unit', 20)->default('piece');
            $table->decimal('weight_kg', 10, 3)->nullable();
            $table->decimal('volume_cm3', 12, 3)->nullable();
            $table->text('description')->nullable();
            $table->string('image', 500)->nullable();
            $table->string('barcode', 100)->nullable()->unique();
            $table->boolean('has_variants')->default(false);
            $table->integer('min_stock_alert')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // ─── Product Variants ──────────────────────────────
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('variant_name');
            $table->string('sku', 100)->unique();
            $table->string('barcode', 100)->nullable()->unique();
            $table->json('attributes')->nullable();
            $table->decimal('weight_kg', 10, 3)->nullable();
            $table->decimal('volume_cm3', 12, 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─── Product Suppliers ─────────────────────────────
        Schema::create('product_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->constrained();
            $table->decimal('price_cny', 12, 2);
            $table->integer('moq')->default(1);
            $table->integer('lead_time_days')->nullable();
            $table->string('supplier_sku', 100)->nullable();
            $table->string('product_url', 500)->nullable();
            $table->boolean('is_preferred')->default(false);
            $table->timestamp('last_purchased_at')->nullable();
            $table->decimal('last_purchase_price_cny', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_suppliers');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('supplier_contacts');
        Schema::dropIfExists('suppliers');
    }
};
