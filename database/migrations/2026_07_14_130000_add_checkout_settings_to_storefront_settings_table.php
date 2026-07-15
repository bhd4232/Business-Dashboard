<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->boolean('cod_enabled')->default(true)->after('online_payment_enabled');
            $table->decimal('delivery_charge_inside', 10, 2)->nullable()->after('cod_enabled');
            $table->decimal('delivery_charge_outside', 10, 2)->nullable()->after('delivery_charge_inside');
            $table->string('manual_bkash_number')->nullable()->after('delivery_charge_outside');
            $table->text('manual_bkash_instructions')->nullable()->after('manual_bkash_number');
            $table->string('manual_nagad_number')->nullable()->after('manual_bkash_instructions');
            $table->text('manual_nagad_instructions')->nullable()->after('manual_nagad_number');
        });
    }

    public function down(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'cod_enabled',
                'delivery_charge_inside',
                'delivery_charge_outside',
                'manual_bkash_number',
                'manual_bkash_instructions',
                'manual_nagad_number',
                'manual_nagad_instructions',
            ]);
        });
    }
};
