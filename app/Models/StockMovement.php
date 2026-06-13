<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class StockMovement extends Model
{
    public const TYPES = [
        'opening' => 'Opening',
        'purchase' => 'Purchase',
        'sale' => 'Sale',
        'return' => 'Return',
        'adjustment' => 'Adjustment',
    ];

    protected $fillable = [
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
        static::saving(function (StockMovement $movement): void {
            $movement->quantity = static::normalizeQuantity($movement->type, (int) $movement->quantity);
            static::validateMovement($movement);
        });

        static::saved(function (StockMovement $movement): void {
            if ($movement->wasChanged('product_id')) {
                static::syncProductStock((int) $movement->getOriginal('product_id'));
            }

            static::syncProductStock($movement->product_id);
        });

        static::deleted(function (StockMovement $movement): void {
            static::syncProductStock($movement->product_id);
        });

        static::deleting(function (StockMovement $movement): void {
            if (! $movement->exists) {
                return;
            }

            $stockAfterDelete = static::query()
                ->where('product_id', $movement->product_id)
                ->whereKeyNot($movement->getKey())
                ->get()
                ->sum(fn (StockMovement $existingMovement): int => $existingMovement->signed_quantity);

            if ($stockAfterDelete < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'This stock movement cannot be removed because product stock would become negative.',
                ]);
            }
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
        return match ($type) {
            'adjustment' => $quantity,
            'sale', 'opening', 'purchase', 'return' => abs($quantity),
            default => $quantity,
        };
    }

    public static function signedQuantityFor(?string $type, int $quantity): int
    {
        return match ($type) {
            'sale' => -abs($quantity),
            'adjustment' => $quantity,
            default => abs($quantity),
        };
    }

    public static function projectedStockFor(
        int $productId,
        string $type,
        int $quantity,
        ?int $excludingMovementId = null,
    ): int {
        $currentStock = static::query()
            ->where('product_id', $productId)
            ->when($excludingMovementId, fn ($query) => $query->whereKeyNot($excludingMovementId))
            ->get()
            ->sum(fn (StockMovement $movement): int => $movement->signed_quantity);

        return $currentStock + static::signedQuantityFor($type, $quantity);
    }

    public static function validateMovement(StockMovement $movement): void
    {
        if (! array_key_exists($movement->type, static::TYPES)) {
            throw ValidationException::withMessages([
                'type' => 'Please select a valid stock movement type.',
            ]);
        }

        if ($movement->type === 'adjustment') {
            if ((int) $movement->quantity === 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Adjustment quantity must be a non-zero signed value.',
                ]);
            }

            if (blank($movement->reason)) {
                throw ValidationException::withMessages([
                    'reason' => 'Please enter a reason for this stock adjustment.',
                ]);
            }
        } elseif ((int) $movement->quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be greater than zero.',
            ]);
        }

        $projectedStock = static::projectedStockFor(
            (int) $movement->product_id,
            $movement->type,
            (int) $movement->quantity,
            $movement->exists ? (int) $movement->getKey() : null,
        );

        if ($projectedStock < 0) {
            $message = $movement->type === 'sale'
                ? 'Insufficient stock for this sale quantity.'
                : 'This stock movement would make product stock negative.';

            throw ValidationException::withMessages([
                'quantity' => $message,
            ]);
        }
    }

    public static function syncProductStock(?int $productId): void
    {
        if (! $productId) {
            return;
        }

        $stock = static::query()
            ->where('product_id', $productId)
            ->get()
            ->sum(fn (StockMovement $movement): int => $movement->signed_quantity);

        Product::query()
            ->whereKey($productId)
            ->update(['stock' => $stock]);
    }
}
