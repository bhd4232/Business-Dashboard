<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Services\StockMovementService;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use BelongsToCompany;

    public const TYPES = [
        'opening' => 'Opening',
        'purchase' => 'Purchase',
        'sale' => 'Sale',
        'return' => 'Return',
        'adjustment' => 'Adjustment',
    ];

    protected $fillable = [
        'company_id',
        'product_id',
        'type',
        'quantity',
        'reason',
        'reference_type',
        'reference_id',
        'note',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (StockMovement $movement): void {
            $movement->company_id ??= $movement->product?->company_id;
        });

        static::saving(function (StockMovement $movement): void {
            app(StockMovementService::class)->prepareForSave($movement);
        });

        static::saved(function (StockMovement $movement): void {
            if ($movement->wasChanged('product_id')) {
                app(StockMovementService::class)->syncProductStock((int) $movement->getOriginal('product_id'));
            }

            app(StockMovementService::class)->syncProductStock($movement->product_id);
        });

        static::deleted(function (StockMovement $movement): void {
            app(StockMovementService::class)->syncProductStock($movement->product_id);
        });

        static::deleting(function (StockMovement $movement): void {
            app(StockMovementService::class)->assertCanDelete($movement);
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getSignedQuantityAttribute(): int
    {
        return static::signedQuantityFor($this->type, $this->quantity);
    }

    public static function normalizeQuantity(?string $type, int $quantity): int
    {
        return app(StockMovementService::class)->normalizeQuantity($type, $quantity);
    }

    public static function signedQuantityFor(?string $type, int $quantity): int
    {
        return app(StockMovementService::class)->signedQuantityFor($type, $quantity);
    }

    public static function projectedStockFor(
        int $productId,
        string $type,
        int $quantity,
        ?int $excludingMovementId = null,
    ): int {
        return app(StockMovementService::class)->projectedStockFor($productId, $type, $quantity, $excludingMovementId);
    }

    public static function validateMovement(StockMovement $movement): void
    {
        app(StockMovementService::class)->validate($movement);
    }

    public static function syncProductStock(?int $productId): void
    {
        app(StockMovementService::class)->syncProductStock($productId);
    }
}
