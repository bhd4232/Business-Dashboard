<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Shipments ─────────────────────────────────────
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_no', 50)->unique();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('carrier')->nullable();
            $table->string('container_no', 50)->nullable();
            $table->string('container_type', 20)->nullable();
            $table->string('bl_number', 100)->nullable();
            $table->enum('shipping_type', ['sea', 'air', 'rail', 'courier']);
            $table->string('port_loading', 100)->nullable();
            $table->string('port_discharge', 100)->nullable();
            $table->date('etd')->nullable();
            $table->date('eta')->nullable();
            $table->date('atd')->nullable();
            $table->date('ata')->nullable();
            $table->enum('status', ['booked', 'loaded', 'departed', 'in_transit', 'arrived', 'clearing', 'cleared', 'delivered_to_warehouse'])->default('booked');
            $table->string('customs_agent')->nullable();
            $table->string('customs_declaration_no', 100)->nullable();
            $table->string('tracking_url', 500)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['status', 'eta'], 'idx_shipments_status_eta');
        });

        // ─── Shipment Items ────────────────────────────────
        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('po_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('quantity');
            $table->decimal('weight_kg', 10, 3)->nullable();
            $table->decimal('volume_cm3', 12, 3)->nullable();
            $table->timestamps();
        });

        // ─── Shipment Costs ────────────────────────────────
        Schema::create('shipment_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->enum('cost_type', ['freight', 'customs_duty', 'vat', 'ait', 'labour', 'transport', 'other']);
            $table->string('description', 255)->nullable();
            $table->decimal('amount_cny', 14, 2)->nullable();
            $table->decimal('amount_usd', 14, 2)->nullable();
            $table->decimal('amount_bdt', 14, 2);
            $table->decimal('exchange_rate', 12, 6)->default(1);
            $table->timestamp('paid_at')->nullable();
            $table->string('voucher_no', 100)->nullable();
            $table->timestamps();
        });

        // ─── Shipment Documents ────────────────────────────
        Schema::create('shipment_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->enum('doc_type', ['bl', 'packing_list', 'invoice', 'certificate', 'customs_declaration', 'other']);
            $table->string('title');
            $table->string('file_path', 500);
            $table->string('file_size_kb', 20)->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });

        // ─── Shipment Status History ───────────────────────
        Schema::create('shipment_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('status', 50);
            $table->text('notes')->nullable();
            $table->string('location', 255)->nullable();
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamp('changed_at');
            $table->timestamps();
        });

        // ─── Landing Cost Allocations ──────────────────────
        Schema::create('landing_cost_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('quantity');
            $table->decimal('weight_kg', 10, 3)->nullable();
            $table->decimal('volume_cm3', 12, 3)->nullable();
            $table->decimal('purchase_price_cny', 12, 2);
            $table->decimal('purchase_price_bdt', 12, 2);
            $table->decimal('allocated_freight_bdt', 12, 4)->default(0);
            $table->decimal('allocated_customs_bdt', 12, 4)->default(0);
            $table->decimal('allocated_vat_bdt', 12, 4)->default(0);
            $table->decimal('allocated_other_bdt', 12, 4)->default(0);
            $table->decimal('landing_cost_per_unit_bdt', 12, 4);
            $table->decimal('total_landing_cost_bdt', 14, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_cost_allocations');
        Schema::dropIfExists('shipment_status_history');
        Schema::dropIfExists('shipment_documents');
        Schema::dropIfExists('shipment_costs');
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipments');
    }
};
