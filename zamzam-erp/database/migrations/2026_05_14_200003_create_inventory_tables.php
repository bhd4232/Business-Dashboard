<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Warehouses ────────────────────────────────────
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─── Stock Items ───────────────────────────────────
        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained();
            $table->integer('quantity')->default(0);
            $table->integer('reserved_qty')->default(0);
            $table->decimal('avg_landing_cost_bdt', 12, 4)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'product_variant_id', 'warehouse_id'], 'stock_items_unique');
            $table->index(['product_id', 'warehouse_id'], 'idx_stock_items_product_warehouse');
        });

        // ─── Stock Transactions ────────────────────────────
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained();
            $table->enum('type', ['in', 'out', 'transfer_in', 'transfer_out', 'adjustment_add', 'adjustment_remove', 'return_in']);
            $table->integer('quantity');
            $table->integer('balance_after');
            $table->decimal('unit_cost_bdt', 12, 4)->nullable();
            $table->string('reference_type', 100)->nullable(); // morphable
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['product_id', 'created_at'], 'idx_stock_transactions_product_date');
        });

        // ─── Stock Transfers ───────────────────────────────
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_no', 50)->unique();
            $table->foreignId('from_warehouse_id')->constrained('warehouses');
            $table->foreignId('to_warehouse_id')->constrained('warehouses');
            $table->enum('status', ['pending', 'in_transit', 'completed', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // ─── Transfer Items ────────────────────────────────
        Schema::create('transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('quantity');
            $table->integer('received_qty')->default(0);
            $table->timestamps();
        });

        // ─── Stock Adjustments ─────────────────────────────
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adjustment_no', 50)->unique();
            $table->foreignId('warehouse_id')->constrained();
            $table->enum('type', ['add', 'remove', 'correction'])->default('correction');
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // ─── Adjustment Items ──────────────────────────────
        Schema::create('adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('quantity_before');
            $table->integer('quantity_adjusted');
            $table->integer('quantity_after');
            $table->timestamps();
        });

        // ─── Barcodes ──────────────────────────────────────
        Schema::create('barcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('barcode', 100)->unique();
            $table->enum('type', ['ean13', 'code128', 'qr', 'custom'])->default('code128');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barcodes');
        Schema::dropIfExists('adjustment_items');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('stock_transactions');
        Schema::dropIfExists('stock_items');
        Schema::dropIfExists('warehouses');
    }
};
