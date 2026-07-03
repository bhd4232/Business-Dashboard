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
        'product_variant_id',
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

            $movement->applyVariantStockDelta();

            app(StockMovementService::class)->syncProductStock($movement->product_id);
        });

        static::deleted(function (StockMovement $movement): void {
            $movement->restoreVariantStock();

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

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * Variant stock is a live counter (not ledger-derived like product
     * stock), so movements tagged with a variant apply their signed
     * quantity as a delta when created or changed.
     */
    public function applyVariantStockDelta(): void
    {
        if (! $this->product_variant_id) {
            return;
        }

        $newSigned = static::signedQuantityFor($this->type, (int) $this->quantity);
        $oldSigned = $this->wasRecentlyCreated
            ? 0
            : static::signedQuantityFor(
                (string) $this->getOriginal('type', $this->type),
                (int) $this->getOriginal('quantity', 0),
            );

        $delta = $newSigned - $oldSigned;

        if ($delta === 0) {
            return;
        }

        $variant = ProductVariant::withoutGlobalScopes()->find($this->product_variant_id);
        $variant?->update(['stock' => max(0, (int) $variant->stock + $delta)]);
    }

    public function restoreVariantStock(): void
    {
        if (! $this->product_variant_id) {
            return;
        }

        $signed = static::signedQuantityFor($this->type, (int) $this->quantity);

        if ($signed === 0) {
            return;
        }

        $variant = ProductVariant::withoutGlobalScopes()->find($this->product_variant_id);
        $variant?->update(['stock' => max(0, (int) $variant->stock - $signed)]);
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
