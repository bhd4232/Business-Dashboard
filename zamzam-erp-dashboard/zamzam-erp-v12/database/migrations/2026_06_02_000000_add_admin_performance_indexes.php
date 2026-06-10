<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['category_id', 'is_active'], 'products_category_active_index');
            $table->index(['brand', 'is_active'], 'products_brand_active_index');
            $table->index(['stock', 'reorder_level'], 'products_stock_reorder_index');
            $table->index('created_at', 'products_created_at_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['customer_id', 'status'], 'orders_customer_status_index');
            $table->index(['status', 'order_date'], 'orders_status_date_index');
            $table->index('order_date', 'orders_order_date_index');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->index(['status', 'purchase_date'], 'purchases_status_date_index');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index(['is_active', 'name'], 'customers_active_name_index');
            $table->index('current_balance', 'customers_current_balance_index');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->index(['is_active', 'name'], 'suppliers_active_name_index');
            $table->index('current_balance', 'suppliers_current_balance_index');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index(['order_id', 'product_id'], 'order_items_order_product_index');
        });

    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_order_product_index');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex('suppliers_active_name_index');
            $table->dropIndex('suppliers_current_balance_index');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_active_name_index');
            $table->dropIndex('customers_current_balance_index');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex('purchases_status_date_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_customer_status_index');
            $table->dropIndex('orders_status_date_index');
            $table->dropIndex('orders_order_date_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_category_active_index');
            $table->dropIndex('products_brand_active_index');
            $table->dropIndex('products_stock_reorder_index');
            $table->dropIndex('products_created_at_index');
        });
    }
};
