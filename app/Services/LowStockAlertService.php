<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LowStockAlertService
{
    public function query(): Builder
    {
        return Product::query()
            ->with('category')
            ->whereColumn('stock', '<=', 'reorder_level');
    }

    public function count(): int
    {
        return (int) $this->query()->count();
    }

    public function products(int $limit = 10): Collection
    {
        return $this->query()
            ->orderBy('stock')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    public function hasAlerts(): bool
    {
        return $this->count() > 0;
    }

    public function message(): string
    {
        $count = $this->count();

        return $count === 1
            ? '1 product is at or below its reorder level.'
            : "{$count} products are at or below their reorder levels.";
    }
}
