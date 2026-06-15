<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Validation\ValidationException;

class StockMovementService
{
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

        $stockAfterDelete = StockMovement::query()
            ->where('product_id', $movement->product_id)
            ->whereKeyNot($movement->getKey())
            ->get()
            ->sum(fn (StockMovement $existingMovement): int => $this->signedQuantityFor(
                $existingMovement->type,
                (int) $existingMovement->quantity,
            ));

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

        $stock = StockMovement::query()
            ->where('product_id', $productId)
            ->get()
            ->sum(fn (StockMovement $movement): int => $this->signedQuantityFor(
                $movement->type,
                (int) $movement->quantity,
            ));

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
        $currentStock = StockMovement::query()
            ->where('product_id', $productId)
            ->when($excludingMovementId, fn ($query) => $query->whereKeyNot($excludingMovementId))
            ->get()
            ->sum(fn (StockMovement $movement): int => $this->signedQuantityFor(
                $movement->type,
                (int) $movement->quantity,
            ));

        return $currentStock + $this->signedQuantityFor($type, $quantity);
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

        $projectedStock = $this->projectedStockFor(
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
