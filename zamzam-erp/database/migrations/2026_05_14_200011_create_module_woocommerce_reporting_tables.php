<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Module Settings ───────────────────────────────
        Schema::create('module_settings', function (Blueprint $table) {
            $table->id();
            $table->string('module', 50)->unique();
            $table->boolean('is_active')->default(false);
            $table->json('settings')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
        });

        // ─── Storefront Settings ───────────────────────────
        Schema::create('storefront_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('module', ['wholesale_storefront', 'retail_storefront']);
            $table->string('settings_key', 100);
            $table->json('settings_value');
            $table->timestamps();

            $table->unique(['module', 'settings_key'], 'storefront_settings_unique');
        });

        // ─── WooCommerce Imports ───────────────────────────
        Schema::create('woocommerce_imports', function (Blueprint $table) {
            $table->id();
            $table->string('store_url', 500);
            $table->enum('store_type', ['wholesale', 'retail', 'both'])->default('both');
            $table->string('consumer_key', 500);
            $table->string('consumer_secret', 500);
            $table->boolean('import_products')->default(false);
            $table->boolean('import_categories')->default(false);
            $table->boolean('import_customers')->default(false);
            $table->boolean('import_orders')->default(false);
            $table->integer('products_total')->default(0);
            $table->integer('products_imported')->default(0);
            $table->integer('categories_total')->default(0);
            $table->integer('categories_imported')->default(0);
            $table->integer('customers_total')->default(0);
            $table->integer('customers_imported')->default(0);
            $table->integer('orders_total')->default(0);
            $table->integer('orders_imported')->default(0);
            $table->integer('error_count')->default(0);
            $table->string('error_report_path', 500)->nullable();
            $table->enum('status', ['connecting', 'scanning', 'ready', 'importing', 'completed', 'failed'])->default('connecting');
            $table->boolean('connection_tested')->default(false);
            $table->text('last_error')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // ─── WooCommerce Import Logs ───────────────────────
        Schema::create('woocommerce_import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('woocommerce_import_id')->constrained()->cascadeOnDelete();
            $table->enum('entity_type', ['product', 'category', 'customer', 'order', 'image']);
            $table->unsignedBigInteger('wc_entity_id')->nullable();
            $table->unsignedBigInteger('erp_entity_id')->nullable();
            $table->string('erp_entity_type', 100)->nullable();
            $table->enum('action', ['created', 'updated', 'skipped', 'failed']);
            $table->json('wc_data')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('woocommerce_import_id', 'idx_wc_import_logs_import');
            $table->index(['entity_type', 'action'], 'idx_wc_import_logs_entity');
        });

        // ─── WooCommerce Mappings ──────────────────────────
        Schema::create('product_wc_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('wc_product_id');
            $table->string('wc_product_sku', 100)->nullable();
            $table->string('wc_store_url', 500);
            $table->timestamp('imported_at');
            $table->timestamp('created_at')->nullable();

            $table->unique(['product_id', 'product_variant_id', 'wc_store_url'], 'product_wc_mappings_unique');
        });

        Schema::create('category_wc_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained();
            $table->unsignedBigInteger('wc_category_id');
            $table->string('wc_store_url', 500);
            $table->timestamp('imported_at');
            $table->timestamp('created_at')->nullable();

            $table->unique(['category_id', 'wc_store_url'], 'category_wc_mappings_unique');
        });

        Schema::create('customer_wc_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->unsignedBigInteger('wc_customer_id');
            $table->string('wc_store_url', 500);
            $table->timestamp('imported_at');
            $table->timestamp('created_at')->nullable();

            $table->unique(['customer_id', 'wc_store_url'], 'customer_wc_mappings_unique');
        });

        // ─── Monthly Reports ───────────────────────────────
        Schema::create('monthly_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_uuid', 50)->unique();
            $table->integer('period_year');
            $table->integer('period_month');
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('type', ['full', 'sales', 'inventory', 'credit', 'shipping'])->default('full');
            $table->enum('status', ['generating', 'ready', 'sent', 'failed'])->default('generating');
            $table->json('data_json');
            $table->json('summary_json')->nullable();
            $table->string('pdf_path', 500)->nullable();
            $table->timestamp('pdf_generated_at')->nullable();
            $table->timestamp('html_generated_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('generation_duration_ms')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('sent_channels')->nullable();
            $table->json('sent_to')->nullable();
            $table->integer('view_count')->default(0);
            $table->timestamps();

            $table->index(['period_year', 'period_month'], 'idx_monthly_reports_period');
            $table->index('status', 'idx_monthly_reports_status');
        });

        // ─── Report Delivery Settings ──────────────────────
        Schema::create('report_delivery_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->enum('channel', ['whatsapp', 'telegram', 'email']);
            $table->string('channel_address');
            $table->boolean('is_active')->default(true);
            $table->enum('report_type', ['full', 'sales_only', 'inventory_only', 'credit_only'])->default('full');
            $table->integer('send_day')->default(1);
            $table->time('send_time')->default('00:01:00');
            $table->boolean('include_pdf_attachment')->default(true);
            $table->boolean('include_dashboard_link')->default(true);
            $table->boolean('include_summary_in_message')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->foreignId('last_report_id')->nullable()->constrained('monthly_reports')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'channel', 'channel_address'], 'report_delivery_unique');
            $table->index(['user_id', 'channel'], 'idx_report_delivery_user_channel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_delivery_settings');
        Schema::dropIfExists('monthly_reports');
        Schema::dropIfExists('customer_wc_mappings');
        Schema::dropIfExists('category_wc_mappings');
        Schema::dropIfExists('product_wc_mappings');
        Schema::dropIfExists('woocommerce_import_logs');
        Schema::dropIfExists('woocommerce_imports');
        Schema::dropIfExists('storefront_settings');
        Schema::dropIfExists('module_settings');
    }
};
