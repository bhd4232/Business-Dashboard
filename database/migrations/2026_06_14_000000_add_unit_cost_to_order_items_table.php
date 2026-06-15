<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('order_items', 'unit_cost')) {
                $table->decimal('unit_cost', 12, 2)->nullable()->after('unit_price');
            }
        });

        if (Schema::hasColumn('order_items', 'unit_cost')) {
            DB::table('order_items')
                ->whereNull('unit_cost')
                ->orderBy('id')
                ->chunkById(100, function ($items): void {
                    $costs = DB::table('products')
                        ->whereIn('id', $items->pluck('product_id')->unique()->all())
                        ->pluck('cost_price', 'id');

                    foreach ($items as $item) {
                        DB::table('order_items')
                            ->where('id', $item->id)
                            ->update(['unit_cost' => $costs[$item->product_id] ?? 0]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            if (Schema::hasColumn('order_items', 'unit_cost')) {
                $table->dropColumn('unit_cost');
            }
        });
    }
};
