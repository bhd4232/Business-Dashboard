<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Courier Providers ─────────────────────────────
        Schema::create('courier_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->string('logo_path', 500)->nullable();
            $table->string('api_url', 500)->nullable();
            $table->string('api_key', 500)->nullable();
            $table->string('api_secret', 500)->nullable();
            $table->boolean('api_enabled')->default(false);
            $table->decimal('default_delivery_charge_inside_bdt', 8, 2)->nullable();
            $table->decimal('default_delivery_charge_outside_bdt', 8, 2)->nullable();
            $table->decimal('cod_charge_percent', 5, 2)->default(0);
            $table->decimal('weight_charge_per_kg_bdt', 8, 2)->nullable();
            $table->decimal('return_charge_bdt', 8, 2)->default(0);
            $table->integer('max_delivery_days')->nullable();
            $table->json('coverage_areas')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─── Delivery Zones ────────────────────────────────
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('zone_name');
            $table->enum('zone_type', ['inside_dhaka', 'outside_dhaka', 'sub_city']);
            $table->string('city', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->json('areas');
            $table->decimal('delivery_charge_bdt', 8, 2);
            $table->decimal('cod_charge_percent', 5, 2)->default(0);
            $table->integer('estimated_delivery_hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['zone_type', 'is_active'], 'idx_delivery_zones_type');
        });

        // ─── Courier Parcels ───────────────────────────────
        Schema::create('courier_parcels', function (Blueprint $table) {
            $table->id();
            $table->string('parcel_no', 50)->unique();
            $table->foreignId('courier_provider_id')->constrained();
            $table->foreignId('sales_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('customer_id')->constrained();
            $table->enum('shipment_type', ['regular', 'express', 'same_day'])->default('regular');
            $table->enum('delivery_type', ['inside_dhaka', 'outside_dhaka', 'sub_city']);
            $table->enum('payment_type', ['prepaid', 'cod']);
            $table->decimal('cod_amount_bdt', 14, 2)->nullable();
            $table->decimal('weight_kg', 8, 3)->nullable();
            $table->string('parcel_content')->nullable();
            $table->decimal('parcel_value_bdt', 14, 2)->nullable();
            $table->integer('number_of_items')->default(1);
            $table->string('sender_name');
            $table->string('sender_phone', 20);
            $table->text('sender_address');
            $table->string('recipient_name');
            $table->string('recipient_phone', 20);
            $table->string('recipient_alt_phone', 20)->nullable();
            $table->text('recipient_address');
            $table->string('recipient_city', 100)->nullable();
            $table->string('recipient_area', 100)->nullable();
            $table->string('recipient_zone', 100)->nullable();
            $table->string('recipient_district', 100)->nullable();
            $table->string('courier_tracking_id')->nullable();
            $table->string('courier_consignment_id')->nullable();
            $table->decimal('delivery_charge_bdt', 8, 2);
            $table->decimal('cod_charge_bdt', 8, 2)->default(0);
            $table->decimal('total_charge_bdt', 8, 2);
            $table->enum('status', ['pending', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'partial_delivery', 'returned', 'cancelled', 'lost'])->default('pending');
            $table->string('courier_status', 50)->nullable();
            $table->timestamp('courier_status_updated_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->string('return_reason')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->string('pod_image_path', 500)->nullable();
            $table->string('pod_signature_path', 500)->nullable();
            $table->foreignId('pod_submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('pod_submitted_at')->nullable();
            $table->integer('delivery_attempt_count')->default(0);
            $table->boolean('label_generated')->default(false);
            $table->string('label_path', 500)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['courier_provider_id', 'status'], 'idx_courier_parcels_provider_status');
            $table->index('customer_id', 'idx_courier_parcels_customer');
            $table->index('sales_order_id', 'idx_courier_parcels_order');
            $table->index('status', 'idx_courier_parcels_status');
        });

        // ─── Courier Parcel Items ──────────────────────────
        Schema::create('courier_parcel_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_parcel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('qty');
            $table->timestamp('created_at')->nullable();
        });

        // ─── Courier Status History ────────────────────────
        Schema::create('courier_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_parcel_id')->constrained()->cascadeOnDelete();
            $table->string('status', 50);
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->json('courier_raw_data')->nullable();
            $table->enum('source', ['manual', 'api_sync']);
            $table->timestamp('changed_at');
            $table->timestamp('created_at')->nullable();
        });

        // ─── Courier Bills ─────────────────────────────────
        Schema::create('courier_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_provider_id')->constrained();
            $table->string('bill_number', 50)->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->integer('total_parcels');
            $table->decimal('total_delivery_charge_bdt', 14, 2);
            $table->decimal('total_cod_charge_bdt', 14, 2)->default(0);
            $table->decimal('total_cod_collected_bdt', 14, 2)->default(0);
            $table->decimal('total_deduction_bdt', 14, 2)->default(0);
            $table->decimal('net_payable_bdt', 14, 2);
            $table->enum('status', ['draft', 'confirmed', 'paid', 'disputed'])->default('draft');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // ─── Courier Bill Items ────────────────────────────
        Schema::create('courier_bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('courier_parcel_id')->constrained();
            $table->decimal('delivery_charge_bdt', 8, 2);
            $table->decimal('cod_charge_bdt', 8, 2)->default(0);
            $table->decimal('cod_collected_bdt', 14, 2)->nullable();
            $table->decimal('deduction_bdt', 8, 2)->default(0);
            $table->string('deduction_reason')->nullable();
            $table->decimal('net_amount_bdt', 14, 2);
            $table->timestamp('created_at')->nullable();
        });

        // ─── Courier Performance Metrics ───────────────────
        Schema::create('courier_performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_provider_id')->constrained();
            $table->enum('period_type', ['daily', 'weekly', 'monthly']);
            $table->date('period_start');
            $table->date('period_end');
            $table->integer('total_parcels');
            $table->integer('delivered_count');
            $table->integer('returned_count')->default(0);
            $table->integer('lost_count')->default(0);
            $table->integer('cancelled_count')->default(0);
            $table->decimal('delivery_success_rate', 5, 2);
            $table->decimal('avg_delivery_hours_inside', 8, 2)->nullable();
            $table->decimal('avg_delivery_hours_outside', 8, 2)->nullable();
            $table->decimal('cod_collected_bdt', 14, 2)->default(0);
            $table->decimal('cod_pending_bdt', 14, 2)->default(0);
            $table->decimal('total_delivery_charge_bdt', 14, 2)->default(0);
            $table->decimal('return_rate_percent', 5, 2)->default(0);
            $table->decimal('on_time_rate_percent', 5, 2)->nullable();
            $table->timestamp('calculated_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['courier_provider_id', 'period_type', 'period_start'], 'idx_courier_performance_period');
        });

        // ─── Fake Order Detections ─────────────────────────
        Schema::create('fake_order_detections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45);
            $table->foreignId('order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->enum('detection_type', ['ip_block', 'duplicate_order', 'suspicious_pattern', 'high_value_cod', 'manual_flag']);
            $table->text('reason');
            $table->enum('action_taken', ['flagged', 'blocked_ip', 'order_cancelled', 'manual_review']);
            $table->boolean('is_resolved')->default(false);
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['is_resolved', 'detection_type'], 'idx_fake_detections_unresolved');
        });

        // ─── IP Blacklist ──────────────────────────────────
        Schema::create('ip_blacklist', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->unique();
            $table->text('reason');
            $table->foreignId('blocked_by')->constrained('users');
            $table->timestamp('blocked_at');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();

            $table->index(['ip_address', 'is_active'], 'idx_ip_blacklist_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_blacklist');
        Schema::dropIfExists('fake_order_detections');
        Schema::dropIfExists('courier_performance_metrics');
        Schema::dropIfExists('courier_bill_items');
        Schema::dropIfExists('courier_bills');
        Schema::dropIfExists('courier_status_history');
        Schema::dropIfExists('courier_parcel_items');
        Schema::dropIfExists('courier_parcels');
        Schema::dropIfExists('delivery_zones');
        Schema::dropIfExists('courier_providers');
    }
};
