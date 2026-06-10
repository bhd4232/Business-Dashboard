<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 — Amend shipping tables:
 *  1. Add cost_allocation_method to shipments
 *  2. Add carton_count to shipment_items
 *  3. Extend shipment_costs.cost_type enum (add customs_fee, demurrage)
 *  4. Add detailed allocation columns to landing_cost_allocations
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. shipments — add cost_allocation_method ──────────────
        Schema::table('shipments', function (Blueprint $table) {
            $table->enum('cost_allocation_method', ['weight', 'volume', 'value', 'quantity', 'manual'])
                  ->default('weight')
                  ->after('status');
        });

        // ── 2. shipment_items — add carton_count ────────────────────
        Schema::table('shipment_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('carton_count')->nullable()->after('quantity');
        });

        // ── 3. shipment_costs — extend enum ─────────────────────────
        // MySQL: ALTER COLUMN to add 'customs_fee' and 'demurrage' values
        \DB::statement("ALTER TABLE shipment_costs MODIFY cost_type ENUM('freight','customs_duty','vat','ait','labour','transport','customs_fee','demurrage','other') NOT NULL");

        // ── 4. landing_cost_allocations — add detailed columns ───────
        Schema::table('landing_cost_allocations', function (Blueprint $table) {
            $table->foreignId('po_item_id')->nullable()->constrained()->nullOnDelete()->after('shipment_id');
            $table->decimal('allocated_ait_bdt',       12, 4)->default(0)->after('allocated_vat_bdt');
            $table->decimal('allocated_labour_bdt',    12, 4)->default(0)->after('allocated_ait_bdt');
            $table->decimal('allocated_transport_bdt', 12, 4)->default(0)->after('allocated_labour_bdt');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn('cost_allocation_method');
        });

        Schema::table('shipment_items', function (Blueprint $table) {
            $table->dropColumn('carton_count');
        });

        \DB::statement("ALTER TABLE shipment_costs MODIFY cost_type ENUM('freight','customs_duty','vat','ait','labour','transport','other') NOT NULL");

        Schema::table('landing_cost_allocations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('po_item_id');
            $table->dropColumn(['allocated_ait_bdt', 'allocated_labour_bdt', 'allocated_transport_bdt']);
        });
    }
};
