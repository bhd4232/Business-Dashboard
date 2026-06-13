<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Purchase extends Model
{
    public const CHINA_TO_BD_COST_FIELDS = [
        'machine_purchase' => 'Machine Purchase',
        'inspection' => 'Inspection',
        'freight_to_ctg' => 'Freight to Ctg',
        'duty' => 'Duty',
        'c_and_f' => 'C&F',
        'misc' => 'Misc',
        'truck' => 'Truck',
        'load_unload' => 'Load & Unload',
        'spare_parts' => 'Spare Parts',
        'cam' => 'CAM',
        'positive_feeder' => 'Positive Feeder',
        'cylinder' => 'Cylinder',
    ];

    public const STATUSES = [
        'draft' => 'Draft',
        'received' => 'Received',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'purchase_number',
        'supplier_id',
        'purchase_date',
        'subtotal',
        'discount',
        'vat',
        'machine_purchase',
        'inspection',
        'freight_to_ctg',
        'duty',
        'c_and_f',
        'misc',
        'truck',
        'load_unload',
        'spare_parts',
        'cam',
        'positive_feeder',
        'cylinder',
        'custom_costs',
        'total_amount',
        'paid_amount',
        'due_amount',
        'status',
        'update_cost_price',
        'note',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'vat' => 'decimal:2',
        'machine_purchase' => 'decimal:2',
        'inspection' => 'decimal:2',
        'freight_to_ctg' => 'decimal:2',
        'duty' => 'decimal:2',
        'c_and_f' => 'decimal:2',
        'misc' => 'decimal:2',
        'truck' => 'decimal:2',
        'load_unload' => 'decimal:2',
        'spare_parts' => 'decimal:2',
        'cam' => 'decimal:2',
        'positive_feeder' => 'decimal:2',
        'cylinder' => 'decimal:2',
        'custom_costs' => 'array',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'update_cost_price' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Purchase $purchase): void {
            $purchase->purchase_number ??= static::nextPurchaseNumber();
            $purchase->purchase_date ??= now()->toDateString();
        });

        static::saved(function (Purchase $purchase): void {
            $purchase->syncTotalsAndStock();

            if ($purchase->wasChanged('supplier_id')) {
                Supplier::find($purchase->getOriginal('supplier_id'))?->syncCurrentBalance();
            }
        });

        static::deleted(function (Purchase $purchase): void {
            $purchase->deleteStockMovements();
            $purchase->syncSupplierBalance();
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public static function nextPurchaseNumber(): string
    {
        return 'PUR-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5));
    }

    public function syncTotalsAndStock(): void
    {
        if (! $this->exists) {
            return;
        }

        $items = $this->items()->get();
        $subtotal = $items->sum(fn (PurchaseItem $item): float => (int) $item->quantity * (float) $item->unit_cost);
        $total = max($subtotal + $this->chinaToBdCostTotal() - (float) $this->discount + (float) $this->vat, 0);
        $due = max($total - (float) $this->paid_amount, 0);

        $changes = [
            'subtotal' => $subtotal,
            'total_amount' => $total,
            'due_amount' => $due,
        ];

        if ($this->subtotal != $subtotal || $this->total_amount != $total || $this->due_amount != $due) {
            $this->forceFill($changes)->saveQuietly();
        }

        $this->syncLandedCosts($items);

        if ($this->status === 'received') {
            $this->syncStockMovements($items);
        } else {
            $this->deleteStockMovements();
        }

        $this->syncSupplierBalance();
    }

    public function chinaToBdCostTotal(): float
    {
        $fixedCosts = collect(self::CHINA_TO_BD_COST_FIELDS)
            ->keys()
            ->sum(fn (string $field): float => (float) ($this->{$field} ?? 0));

        return $fixedCosts + $this->customCostTotal();
    }

    public function customCostTotal(): float
    {
        return collect($this->custom_costs ?? [])
            ->sum(fn (array $cost): float => (float) ($cost['amount'] ?? 0));
    }

    public function customCostAmountFor(string $label): float
    {
        return collect($this->custom_costs ?? [])
            ->filter(fn (array $cost): bool => ($cost['label'] ?? null) === $label)
            ->sum(fn (array $cost): float => (float) ($cost['amount'] ?? 0));
    }

    public function customCostsSummary(): string
    {
        return collect($this->custom_costs ?? [])
            ->filter(fn (array $cost): bool => filled($cost['label'] ?? null))
            ->map(fn (array $cost): string => ($cost['label'] ?? '') . ': BDT ' . number_format((float) ($cost['amount'] ?? 0), 2))
            ->implode('; ') ?: '-';
    }

    public function landedCostTotal(): float
    {
        return (float) $this->subtotal + $this->chinaToBdCostTotal();
    }

    public function landedCostPerUnitSummary(): string
    {
        return $this->items()
            ->with('product')
            ->get()
            ->map(fn (PurchaseItem $item): string => ($item->product?->name ?? 'Product') . ': BDT ' . number_format((float) $item->landed_unit_cost, 2))
            ->implode('; ') ?: '-';
    }

    protected function syncLandedCosts($items): void
    {
        $extraCostTotal = $this->chinaToBdCostTotal();
        $subtotal = $items->sum(fn (PurchaseItem $item): float => (float) $item->subtotal);
        $remainingAllocatedCost = round($extraCostTotal, 2);
        $lastItemKey = $items->keys()->last();

        foreach ($items as $key => $item) {
            $allocatedCost = 0.0;

            if ($subtotal > 0 && $extraCostTotal > 0) {
                $allocatedCost = $key === $lastItemKey
                    ? $remainingAllocatedCost
                    : round($extraCostTotal * ((float) $item->subtotal / $subtotal), 2);
            }

            $remainingAllocatedCost = round($remainingAllocatedCost - $allocatedCost, 2);
            $quantity = max((int) $item->quantity, 1);
            $landedUnitCost = round(((float) $item->subtotal + $allocatedCost) / $quantity, 2);

            if (
                (float) $item->allocated_cost !== $allocatedCost ||
                (float) $item->landed_unit_cost !== $landedUnitCost
            ) {
                $item->forceFill([
                    'allocated_cost' => $allocatedCost,
                    'landed_unit_cost' => $landedUnitCost,
                ])->saveQuietly();
            }
        }
    }

    protected function syncStockMovements($items): void
    {
        $quantitiesByProduct = $items
            ->groupBy('product_id')
            ->map(fn ($productItems): int => $productItems->sum('quantity'));

        foreach ($quantitiesByProduct as $productId => $quantity) {
            StockMovement::query()->updateOrCreate(
                [
                    'product_id' => $productId,
                    'type' => 'purchase',
                    'reference_type' => self::class,
                    'reference_id' => $this->getKey(),
                ],
                [
                    'quantity' => $quantity,
                    'note' => "Purchase {$this->purchase_number}",
                ],
            );
        }

        StockMovement::query()
            ->where('type', 'purchase')
            ->where('reference_type', self::class)
            ->where('reference_id', $this->getKey())
            ->whereNotIn('product_id', $quantitiesByProduct->keys()->all())
            ->get()
            ->each
            ->delete();

        if ($this->update_cost_price) {
            $items->each(function (PurchaseItem $item): void {
                $item->product?->update(['cost_price' => $item->landed_unit_cost ?: $item->unit_cost]);
            });
        }
    }

    protected function deleteStockMovements(): void
    {
        StockMovement::query()
            ->where('type', 'purchase')
            ->where('reference_type', self::class)
            ->where('reference_id', $this->getKey())
            ->get()
            ->each
            ->delete();
    }

    public function syncSupplierBalance(): void
    {
        $supplier = $this->supplier;

        if (! $supplier) {
            return;
        }

        $supplier->syncCurrentBalance();
    }
}
