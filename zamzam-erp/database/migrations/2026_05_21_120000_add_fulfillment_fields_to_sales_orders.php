<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            // Fulfilment / cancellation reasons
            $table->string('cancel_reason', 255)->nullable()->after('internal_notes');
            $table->string('on_hold_reason', 255)->nullable()->after('cancel_reason');
            $table->string('flag_reason', 100)->nullable()
                  ->comment('pending_return,returned,damaged')
                  ->after('on_hold_reason');

            // Delivery partner info
            $table->string('delivery_partner', 100)->nullable()->after('flag_reason');
            $table->string('delivery_partner_id', 100)->nullable()->after('delivery_partner');
            $table->string('delivery_partner_status', 100)->nullable()->after('delivery_partner_id');
            $table->string('delivery_type', 20)->nullable()->default('regular')
                  ->comment('regular,express')
                  ->after('delivery_partner_status');

            // Status timestamps
            $table->timestamp('shipping_at')->nullable()->after('confirmed_at');
            $table->timestamp('delivered_at')->nullable()->after('shipping_at');
        });

        // Extend status enum to include on_hold & flagged
        DB::statement("
            ALTER TABLE sales_orders
            MODIFY COLUMN status
            ENUM('draft','on_hold','confirmed','processing','picked','dispatched','delivered','flagged','cancelled','returned')
            NOT NULL DEFAULT 'draft'
        ");
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn([
                'cancel_reason', 'on_hold_reason', 'flag_reason',
                'delivery_partner', 'delivery_partner_id', 'delivery_partner_status', 'delivery_type',
                'shipping_at', 'delivered_at',
            ]);
        });

        DB::statement("
            ALTER TABLE sales_orders
            MODIFY COLUMN status
            ENUM('draft','confirmed','processing','picked','dispatched','delivered','cancelled','returned')
            NOT NULL DEFAULT 'draft'
        ");
    }
};
