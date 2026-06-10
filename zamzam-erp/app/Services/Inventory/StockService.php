<?php

namespace App\Services\Inventory;

use App\Enums\AdjustmentType;
use App\Enums\StockTransactionType;
use App\Enums\TransferStatus;
use App\Models\Core\Product;
use App\Models\Inventory\AdjustmentItem;
use App\Models\Inventory\Barcode;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockItem;
use App\Models\Inventory\StockTransaction;
use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\TransferItem;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    /**
     * Get or create stock item for product/variant/warehouse combo.
     */
    public function getOrCreateStockItem(int $productId, ?int $variantId, int $warehouseId): StockItem
    {
        return StockItem::firstOrCreate(
            [
                'product_id'         => $productId,
                'product_variant_id' => $variantId,
                'warehouse_id'       => $warehouseId,
            ],
            [
                'quantity'             => 0,
                'reserved_qty'         => 0,
                'avg_landing_cost_bdt' => 0,
            ]
        );
    }

    /**
     * Receive goods into warehouse (increase stock with weighted avg cost).
     */
    public function receiveGoods(
        int $productId,
        ?int $variantId,
        int $warehouseId,
        int $qty,
        float $unitCostBdt,
        string $referenceType,
        int $referenceId,
        int $createdBy
    ): StockItem {
        return DB::transaction(function () use (
            $productId, $variantId, $warehouseId,
            $qty, $unitCostBdt, $referenceType, $referenceId, $createdBy
        ) {
            $stockItem = $this->getOrCreateStockItem($productId, $variantId, $warehouseId);

            // Weighted average cost
            $existingTotal = $stockItem->quantity * $stockItem->avg_landing_cost_bdt;
            $newTotal      = $qty * $unitCostBdt;
            $newQty        = $stockItem->quantity + $qty;
            $newAvgCost    = $newQty > 0 ? ($existingTotal + $newTotal) / $newQty : $unitCostBdt;

            $stockItem->lockForUpdate();
            $stockItem->update([
                'quantity'             => $newQty,
                'avg_landing_cost_bdt' => $newAvgCost,
            ]);

            StockTransaction::create([
                'product_id'         => $productId,
                'product_variant_id' => $variantId,
                'warehouse_id'       => $warehouseId,
                'type'               => StockTransactionType::In,
                'quantity'           => $qty,
                'balance_after'      => $newQty,
                'unit_cost_bdt'      => $unitCostBdt,
                'reference_type'     => $referenceType,
                'reference_id'       => $referenceId,
                'created_by'         => $createdBy,
            ]);

            return $stockItem->refresh();
        });
    }

    /**
     * Deduct stock for sale (out transaction).
     */
    public function deductStock(
        int $productId,
        ?int $variantId,
        int $warehouseId,
        int $qty,
        string $referenceType,
        int $referenceId,
        int $createdBy
    ): StockItem {
        return DB::transaction(function () use (
            $productId, $variantId, $warehouseId,
            $qty, $referenceType, $referenceId, $createdBy
        ) {
            $stockItem = StockItem::where([
                'product_id'         => $productId,
                'product_variant_id' => $variantId,
                'warehouse_id'       => $warehouseId,
            ])->lockForUpdate()->firstOrFail();

            if ($stockItem->quantity < $qty) {
                throw ValidationException::withMessages([
                    'qty' => "Insufficient stock. Available: {$stockItem->quantity}, Required: {$qty}",
                ]);
            }

            $newQty = $stockItem->quantity - $qty;
            $stockItem->update(['quantity' => $newQty]);

            StockTransaction::create([
                'product_id'         => $productId,
                'product_variant_id' => $variantId,
                'warehouse_id'       => $warehouseId,
                'type'               => StockTransactionType::Out,
                'quantity'           => $qty,
                'balance_after'      => $newQty,
                'unit_cost_bdt'      => $stockItem->avg_landing_cost_bdt,
                'reference_type'     => $referenceType,
                'reference_id'       => $referenceId,
                'created_by'         => $createdBy,
            ]);

            return $stockItem->refresh();
        });
    }

    /**
     * Reserve stock for a sales order.
     */
    public function reserveStock(int $productId, ?int $variantId, int $warehouseId, int $qty): void
    {
        DB::transaction(function () use ($productId, $variantId, $warehouseId, $qty) {
            $stockItem = StockItem::where([
                'product_id'         => $productId,
                'product_variant_id' => $variantId,
                'warehouse_id'       => $warehouseId,
            ])->lockForUpdate()->firstOrFail();

            $available = $stockItem->quantity - $stockItem->reserved_qty;
            if ($available < $qty) {
                throw ValidationException::withMessages([
                    'qty' => 'Insufficient available stock to reserve.',
                ]);
            }

            $stockItem->increment('reserved_qty', $qty);
        });
    }

    /**
     * Release reserved stock.
     */
    public function releaseReservation(int $productId, ?int $variantId, int $warehouseId, int $qty): void
    {
        DB::transaction(function () use ($productId, $variantId, $warehouseId, $qty) {
            $stockItem = StockItem::where([
                'product_id'         => $productId,
                'product_variant_id' => $variantId,
                'warehouse_id'       => $warehouseId,
            ])->lockForUpdate()->first();

            if ($stockItem) {
                $newReserved = max(0, $stockItem->reserved_qty - $qty);
                $stockItem->update(['reserved_qty' => $newReserved]);
            }
        });
    }

    /**
     * Create a stock transfer (pending → in_transit → completed).
     */
    public function createTransfer(array $data, int $createdBy): StockTransfer
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $transfer = StockTransfer::create([
                'transfer_no'       => $this->generateTransferNo(),
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id'   => $data['to_warehouse_id'],
                'status'            => TransferStatus::Pending,
                'notes'             => $data['notes'] ?? null,
                'created_by'        => $createdBy,
            ]);

            foreach ($data['items'] as $item) {
                $transfer->items()->create([
                    'product_id'         => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'quantity'           => $item['quantity'],
                    'received_qty'       => 0,
                ]);
            }

            return $transfer->fresh(['items', 'fromWarehouse', 'toWarehouse']);
        });
    }

    /**
     * Complete a stock transfer — deduct from source, add to destination.
     */
    public function completeTransfer(StockTransfer $transfer, int $userId): StockTransfer
    {
        if (! $transfer->canBeCompleted()) {
            throw ValidationException::withMessages([
                'status' => 'Transfer cannot be completed in its current state.',
            ]);
        }

        return DB::transaction(function () use ($transfer, $userId) {
            foreach ($transfer->items as $item) {
                // Deduct from source
                $this->deductStock(
                    $item->product_id,
                    $item->product_variant_id,
                    $transfer->from_warehouse_id,
                    $item->quantity,
                    StockTransfer::class,
                    $transfer->id,
                    $userId
                );

                // Get current cost from source before deducting
                $sourceCost = StockItem::where([
                    'product_id'         => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id'       => $transfer->from_warehouse_id,
                ])->value('avg_landing_cost_bdt') ?? 0;

                // Add to destination
                $this->receiveGoods(
                    $item->product_id,
                    $item->product_variant_id,
                    $transfer->to_warehouse_id,
                    $item->quantity,
                    $sourceCost,
                    StockTransfer::class,
                    $transfer->id,
                    $userId
                );

                $item->update(['received_qty' => $item->quantity]);
            }

            $transfer->update(['status' => TransferStatus::Completed]);

            return $transfer->refresh();
        });
    }

    /**
     * Apply stock adjustment.
     */
    public function applyAdjustment(array $data, int $createdBy): StockAdjustment
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $adjustment = StockAdjustment::create([
                'adjustment_no' => $this->generateAdjustmentNo(),
                'warehouse_id'  => $data['warehouse_id'],
                'type'          => $data['type'],
                'reason'        => $data['reason'],
                'notes'         => $data['notes'] ?? null,
                'created_by'    => $createdBy,
            ]);

            foreach ($data['items'] as $item) {
                $stockItem = $this->getOrCreateStockItem(
                    $item['product_id'],
                    $item['product_variant_id'] ?? null,
                    $data['warehouse_id']
                );

                $qtyBefore   = $stockItem->quantity;
                $qtyAfter    = $item['quantity_adjusted'];
                $difference  = $qtyAfter - $qtyBefore;

                $adjustment->items()->create([
                    'product_id'         => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'quantity_before'    => $qtyBefore,
                    'quantity_adjusted'  => abs($difference),
                    'quantity_after'     => $qtyAfter,
                ]);

                $stockItem->lockForUpdate();
                $stockItem->update(['quantity' => $qtyAfter]);

                $txType = $difference >= 0
                    ? StockTransactionType::AdjustmentAdd
                    : StockTransactionType::AdjustmentRemove;

                StockTransaction::create([
                    'product_id'         => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'warehouse_id'       => $data['warehouse_id'],
                    'type'               => $txType,
                    'quantity'           => abs($difference),
                    'balance_after'      => $qtyAfter,
                    'unit_cost_bdt'      => $stockItem->avg_landing_cost_bdt,
                    'reference_type'     => StockAdjustment::class,
                    'reference_id'       => $adjustment->id,
                    'created_by'         => $createdBy,
                ]);
            }

            return $adjustment->fresh(['items', 'warehouse']);
        });
    }

    /**
     * Get low stock products across all warehouses.
     */
    public function getLowStockItems(): \Illuminate\Database\Eloquent\Collection
    {
        return StockItem::with(['product', 'variant', 'warehouse'])
            ->whereHas('product', function ($q) {
                $q->whereColumn('stock_items.quantity', '<=', 'products.min_stock_alert')
                  ->where('min_stock_alert', '>', 0);
            })
            ->get();
    }

    /**
     * Get total stock valuation.
     */
    public function getStockValuation(?int $warehouseId = null): float
    {
        $query = StockItem::query();
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }
        return (float) $query->selectRaw('SUM(quantity * avg_landing_cost_bdt) as total')->value('total');
    }

    private function generateTransferNo(): string
    {
        $year   = now()->format('Y');
        $prefix = "ST-{$year}-";
        $last   = StockTransfer::where('transfer_no', 'like', "{$prefix}%")
                    ->orderByDesc('transfer_no')->value('transfer_no');
        $next   = $last ? ((int) substr($last, -4)) + 1 : 1;
        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    private function generateAdjustmentNo(): string
    {
        $year   = now()->format('Y');
        $prefix = "SA-{$year}-";
        $last   = StockAdjustment::where('adjustment_no', 'like', "{$prefix}%")
                    ->orderByDesc('adjustment_no')->value('adjustment_no');
        $next   = $last ? ((int) substr($last, -4)) + 1 : 1;
        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
