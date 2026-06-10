<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Purchase Orders ───────────────────────────────
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number', 50)->unique();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('currency_id')->constrained('currencies');
            $table->decimal('exchange_rate', 12, 6)->default(0);
            $table->enum('status', ['draft', 'confirmed', 'partially_shipped', 'shipped', 'received', 'completed', 'cancelled'])->default('draft');
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->decimal('subtotal_cny', 14, 2)->default(0);
            $table->decimal('total_cny', 14, 2)->default(0);
            $table->decimal('total_bdt', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['supplier_id', 'status'], 'idx_purchase_orders_supplier_status');
        });

        // ─── PO Items ──────────────────────────────────────
        Schema::create('po_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('supplier_price_cny', 12, 2);
            $table->integer('quantity');
            $table->decimal('subtotal_cny', 14, 2);
            $table->integer('received_qty')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // ─── Product Price History ─────────────────────────
        Schema::create('product_price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('purchase_order_id')->constrained();
            $table->decimal('price_cny', 12, 2);
            $table->decimal('price_bdt', 12, 2);
            $table->decimal('exchange_rate', 12, 6);
            $table->integer('qty');
            $table->date('recorded_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['product_id', 'recorded_at'], 'idx_product_price_history_product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_history');
        Schema::dropIfExists('po_items');
        Schema::dropIfExists('purchase_orders');
    }
};
