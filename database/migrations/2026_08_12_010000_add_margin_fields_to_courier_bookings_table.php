<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courier_bookings', function (Blueprint $table): void {
            $table->decimal('delivery_fee_charged', 12, 2)->nullable()->after('cod_amount');
            $table->decimal('delivery_cost', 12, 2)->nullable()->after('delivery_fee_charged');
            $table->decimal('cod_charge_amount', 12, 2)->nullable()->after('delivery_cost');
            $table->decimal('margin', 12, 2)->nullable()->after('cod_charge_amount');
        });
    }

    public function down(): void
    {
        Schema::table('courier_bookings', function (Blueprint $table): void {
            $table->dropColumn(['delivery_fee_charged', 'delivery_cost', 'cod_charge_amount', 'margin']);
        });
    }
};
