<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Price Tiers ───────────────────────────────────
        Schema::create('price_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 20)->unique();
            $table->text('description')->nullable();
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // ─── Product Price Tiers ───────────────────────────
        Schema::create('product_price_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('price_tier_id')->constrained()->cascadeOnDelete();
            $table->decimal('price_bdt', 12, 2);
            $table->integer('min_qty')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'product_variant_id', 'price_tier_id', 'min_qty'], 'product_price_tier_unique');
        });

        // ─── Customers ─────────────────────────────────────
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_code', 50)->unique();
            $table->string('external_id', 50)->nullable()->unique();
            $table->string('name');
            $table->string('business_name')->nullable();
            $table->enum('type', ['wholesale', 'retail']);
            $table->string('phone', 20)->unique();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('area', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('trade_license_no', 100)->nullable();
            $table->string('nid_no', 50)->nullable();
            $table->string('photo', 500)->nullable();
            $table->decimal('credit_limit_bdt', 14, 2)->default(0);
            $table->decimal('outstanding_balance_bdt', 14, 2)->default(0);
            $table->foreignId('price_tier_id')->nullable()->constrained('price_tiers')->nullOnDelete();
            $table->string('source', 50)->nullable();
            $table->string('source_detail')->nullable();
            $table->tinyInteger('rating')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('assigned_salesman_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_order_at')->nullable();
            $table->integer('total_orders')->default(0);
            $table->decimal('total_delivered_value_bdt', 14, 2)->default(0);
            $table->integer('sms_count')->default(0);
            $table->unsignedBigInteger('woo_customer_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('phone', 'idx_customers_phone');
            $table->index('source', 'idx_customers_source');
            $table->index('type', 'idx_customers_type');
            $table->index('last_order_at', 'idx_customers_last_order');
            $table->index('price_tier_id', 'idx_customers_price_tier');
        });

        // ─── Customer Tags ─────────────────────────────────
        Schema::create('customer_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('color', 7)->default('#6366F1');
            $table->text('description')->nullable();
            $table->boolean('is_auto_assign')->default(false);
            $table->json('auto_assign_condition')->nullable();
            $table->foreignId('linked_price_tier_id')->nullable()->constrained('price_tiers')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->integer('customers_count')->default(0);
            $table->timestamps();
        });

        // ─── Customer Tag Pivot ────────────────────────────
        Schema::create('customer_customer_tag', function (Blueprint $table) {
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_tag_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->primary(['customer_id', 'customer_tag_id']);
        });

        // ─── ID Format Settings ────────────────────────────
        Schema::create('id_format_settings', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 50)->unique();
            $table->string('prefix', 10)->default('');
            $table->string('suffix', 10)->default('');
            $table->string('separator', 5)->default('-');
            $table->boolean('include_year')->default(false);
            $table->string('year_format', 4)->default('YYYY');
            $table->boolean('include_month')->default(false);
            $table->integer('sequence_digits')->default(4);
            $table->integer('sequence_start')->default(1);
            $table->boolean('reset_annually')->default(false);
            $table->integer('current_sequence')->default(1);
            $table->string('preview_example', 50)->nullable();
            $table->timestamps();
        });

        // ─── Data Imports ──────────────────────────────────
        Schema::create('data_imports', function (Blueprint $table) {
            $table->id();
            $table->enum('entity_type', ['customers', 'products', 'suppliers']);
            $table->string('original_filename');
            $table->string('file_path', 500)->nullable();
            $table->integer('file_size_kb')->nullable();
            $table->integer('total_rows');
            $table->integer('imported_count')->default(0);
            $table->integer('updated_count')->default(0);
            $table->integer('skipped_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->enum('duplicate_action', ['skip', 'update', 'create_new'])->default('skip');
            $table->json('column_mapping');
            $table->json('tag_mapping')->nullable();
            $table->json('source_mapping')->nullable();
            $table->json('default_values')->nullable();
            $table->string('error_report_path', 500)->nullable();
            $table->enum('status', ['uploading', 'mapping', 'validating', 'importing', 'completed', 'failed'])->default('uploading');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('entity_type', 'idx_data_imports_entity');
            $table->index('status', 'idx_data_imports_status');
        });

        // ─── Data Import Errors ────────────────────────────
        Schema::create('data_import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_import_id')->constrained()->cascadeOnDelete();
            $table->integer('row_number');
            $table->enum('error_type', ['validation', 'duplicate', 'format', 'missing_required', 'unknown']);
            $table->string('field_name', 100)->nullable();
            $table->text('field_value')->nullable();
            $table->text('error_message');
            $table->json('raw_row_data')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('data_import_id', 'idx_data_import_errors_import');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_import_errors');
        Schema::dropIfExists('data_imports');
        Schema::dropIfExists('id_format_settings');
        Schema::dropIfExists('customer_customer_tag');
        Schema::dropIfExists('customer_tags');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('product_price_tiers');
        Schema::dropIfExists('price_tiers');
    }
};
