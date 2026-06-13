<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_items', 'allocated_cost')) {
                $table->decimal('allocated_cost', 12, 2)->default(0)->after('subtotal');
            }

            if (! Schema::hasColumn('purchase_items', 'landed_unit_cost')) {
                $table->decimal('landed_unit_cost', 12, 2)->default(0)->after('allocated_cost');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            foreach (['landed_unit_cost', 'allocated_cost'] as $column) {
                if (Schema::hasColumn('purchase_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
