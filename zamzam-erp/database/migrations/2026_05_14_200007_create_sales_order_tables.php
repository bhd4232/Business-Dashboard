<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Sales Orders ──────────────────────────────────
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 50)->unique();
            $table->foreignId('customer_id')->constrained();
            $table->enum('type', ['wholesale', 'retail'])->default('wholesale');
            $table->enum('source', ['erp', 'storefront', 'whatsapp', 'messenger', 'woocommerce', 'reseller'])->default('erp');
            $table->enum('status', ['draft', 'confirmed', 'processing', 'picked', 'dispatched', 'delivered', 'cancelled', 'returned'])->default('draft');
            $table->foreignId('price_tier_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('subtotal_bdt', 14, 2)->default(0);
            $table->decimal('discount_bdt', 14, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('delivery_charge_bdt', 10, 2)->default(0);
            $table->decimal('total_bdt', 14, 2)->default(0);
            $table->decimal('paid_bdt', 14, 2)->default(0);
            $table->decimal('due_bdt', 14, 2)->default(0);
            $table->text('delivery_address')->nullable();
            $table->string('delivery_city', 100)->nullable();
            $table->string('delivery_area', 100)->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['customer_id', 'status'], 'idx_sales_orders_customer_status');
            $table->index(['type', 'source'], 'idx_sales_orders_type_source');
        });

        // ─── SO Items ──────────────────────────────────────
        Schema::create('so_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price_bdt', 12, 2);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('subtotal_bdt', 14, 2);
            $table->decimal('unit_landing_cost_bdt', 12, 4)->default(0);
            $table->timestamps();
        });

        // ─── Invoices ──────────────────────────────────────
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 50)->unique();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained();
            $table->enum('status', ['draft', 'issued', 'partial', 'paid', 'overdue', 'cancelled'])->default('draft');
            $table->decimal('subtotal_bdt', 14, 2);
            $table->decimal('discount_bdt', 14, 2)->default(0);
            $table->decimal('delivery_charge_bdt', 10, 2)->default(0);
            $table->decimal('total_bdt', 14, 2);
            $table->decimal('paid_bdt', 14, 2)->default(0);
            $table->decimal('due_bdt', 14, 2);
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['status', 'due_date'], 'idx_invoices_status_due');
        });

        // ─── Invoice Items ─────────────────────────────────
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price_bdt', 12, 2);
            $table->decimal('subtotal_bdt', 14, 2);
            $table->timestamps();
        });

        // ─── Sales Returns ─────────────────────────────────
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_no', 50)->unique();
            $table->foreignId('sales_order_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->enum('status', ['pending', 'approved', 'received', 'refunded', 'cancelled'])->default('pending');
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('refund_amount_bdt', 14, 2)->default(0);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // ─── Return Items ──────────────────────────────────
        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price_bdt', 12, 2);
            $table->decimal('subtotal_bdt', 14, 2);
            $table->enum('condition', ['good', 'damaged', 'expired'])->default('good');
            $table->boolean('restock')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('sales_returns');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('so_items');
        Schema::dropIfExists('sales_orders');
    }
};
