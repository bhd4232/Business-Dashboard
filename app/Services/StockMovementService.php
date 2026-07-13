<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class StockMovementService
{
    /**
     * Signed on-hand stock for the movements matched by $query, summed in SQL.
     * Mirrors signedQuantityFor(): sales subtract, adjustments keep their sign,
     * everything else adds. Aggregating in the database avoids loading a
     * product's entire movement history into memory on every recompute.
     */
    protected function signedStockSum(Builder $query): int
    {
        return (int) $query
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN type = 'sale' THEN -ABS(quantity) "
                ."WHEN type = 'adjustment' THEN quantity ELSE ABS(quantity) END), 0) as signed_stock"
            )
            ->value('signed_stock');
    }

    public function prepareForSave(StockMovement $movement): void
    {
        $movement->quantity = $this->normalizeQuantity($movement->type, (int) $movement->quantity);

        $this->validate($movement);
    }

    public function assertCanDelete(StockMovement $movement): void
    {
        if (! $movement->exists) {
            return;
        }

        $stockAfterDelete = $this->signedStockSum(
            StockMovement::query()
                ->where('product_id', $movement->product_id)
                ->whereKeyNot($movement->getKey())
        );

        if ($stockAfterDelete < 0) {
            throw ValidationException::withMessages([
                'quantity' => 'This stock movement cannot be removed because product stock would become negative.',
            ]);
        }
    }

    public function syncProductStock(?int $productId): void
    {
        if (! $productId) {
            return;
        }

        // Variable products own their stock as the sum of active variant
        // stock (synced by ProductVariant hooks) — the movement ledger
        // must not overwrite it.
        $hasVariants = (bool) Product::query()
            ->withoutGlobalScopes()
            ->whereKey($productId)
            ->value('has_variants');

        if ($hasVariants) {
            return;
        }

        $stock = $this->signedStockSum(
            StockMovement::query()->where('product_id', $productId)
        );

        Product::query()
            ->whereKey($productId)
            ->update(['stock' => $stock]);
    }

    public function normalizeQuantity(?string $type, int $quantity): int
    {
        return match ($type) {
            'adjustment' => $quantity,
            'sale', 'opening', 'purchase', 'return' => abs($quantity),
            default => $quantity,
        };
    }

    public function signedQuantityFor(?string $type, int $quantity): int
    {
        return match ($type) {
            'sale' => -abs($quantity),
            'adjustment' => $quantity,
            default => abs($quantity),
        };
    }

    public function projectedStockFor(
        int $productId,
        string $type,
        int $quantity,
        ?int $excludingMovementId = null,
    ): int {
        $currentStock = $this->signedStockSum(
            StockMovement::query()
                ->where('product_id', $productId)
                ->when($excludingMovementId, fn ($query) => $query->whereKeyNot($excludingMovementId))
        );

        return $currentStock + $this->signedQuantityFor($type, $quantity);
    }

    public function projectedVariantStockFor(StockMovement $movement): int
    {
        $variant = ProductVariant::withoutGlobalScopes()->find($movement->product_variant_id);

        if (! $variant || (int) $variant->product_id !== (int) $movement->product_id) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'Please select a valid product variant.',
            ]);
        }

        $oldSigned = 0;

        if (
            $movement->exists
            && (int) $movement->getOriginal('product_variant_id') === (int) $movement->product_variant_id
        ) {
            $oldSigned = $this->signedQuantityFor(
                (string) $movement->getOriginal('type', $movement->type),
                (int) $movement->getOriginal('quantity', 0),
            );
        }

        return (int) $variant->stock
            + $this->signedQuantityFor($movement->type, (int) $movement->quantity)
            - $oldSigned;
    }

    public function validate(StockMovement $movement): void
    {
        if (! array_key_exists($movement->type, StockMovement::TYPES)) {
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

        $projectedStock = $movement->product_variant_id
            ? $this->projectedVariantStockFor($movement)
            : $this->projectedStockFor(
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
}
