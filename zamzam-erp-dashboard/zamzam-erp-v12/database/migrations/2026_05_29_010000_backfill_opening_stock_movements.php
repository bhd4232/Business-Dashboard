<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('products')
            ->select(['products.id', 'products.stock', 'products.created_at'])
            ->where('products.stock', '!=', 0)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('stock_movements')
                    ->whereColumn('stock_movements.product_id', 'products.id');
            })
            ->orderBy('products.id')
            ->chunkById(500, function ($products) use ($now): void {
                $rows = $products->map(fn ($product): array => [
                    'product_id' => $product->id,
                    'type' => 'opening',
                    'quantity' => $product->stock,
                    'reference_type' => 'opening_stock_backfill',
                    'reference_id' => $product->id,
                    'note' => 'Backfilled from existing product stock during inventory migration.',
                    'created_at' => $product->created_at ?? $now,
                    'updated_at' => $now,
                ])->all();

                DB::table('stock_movements')->insert($rows);
            }, 'products.id', 'id');
    }

    public function down(): void
    {
        DB::table('stock_movements')
            ->where('reference_type', 'opening_stock_backfill')
            ->delete();
    }
};
