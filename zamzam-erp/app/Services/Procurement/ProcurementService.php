<?php

namespace App\Services\Procurement;

use App\Enums\PoStatus;
use App\Models\Procurement\PoItem;
use App\Models\Procurement\ProductPriceHistory;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcurementService
{
    /**
     * Generate next PO number: PO-2026-0001
     */
    public function generatePoNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "PO-{$year}-";

        $last = PurchaseOrder::where('po_number', 'like', "{$prefix}%")
            ->orderByDesc('po_number')
            ->value('po_number');

        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create a purchase order with items.
     */
    public function createPurchaseOrder(array $data, int $createdBy): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $po = PurchaseOrder::create([
                'po_number'              => $this->generatePoNumber(),
                'supplier_id'            => $data['supplier_id'],
                'currency_id'            => $data['currency_id'],
                'exchange_rate'          => $data['exchange_rate'],
                'status'                 => PoStatus::Draft,
                'order_date'             => $data['order_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'notes'                  => $data['notes'] ?? null,
                'terms_and_conditions'   => $data['terms_and_conditions'] ?? null,
                'created_by'             => $createdBy,
            ]);

            foreach ($data['items'] as $item) {
                $po->items()->create([
                    'product_id'         => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'supplier_price_cny' => $item['supplier_price_cny'],
                    'quantity'           => $item['quantity'],
                    'approx_weight_kg'   => isset($item['approx_weight_kg']) && $item['approx_weight_kg'] > 0 ? $item['approx_weight_kg'] : null,
                    'subtotal_cny'       => $item['supplier_price_cny'] * $item['quantity'],
                    'notes'              => $item['notes'] ?? null,
                ]);
            }

            $po->recalculateTotals();

            return $po->fresh(['items', 'supplier']);
        });
    }

    /**
     * Confirm a PO (Draft → Confirmed).
     */
    public function confirmPurchaseOrder(PurchaseOrder $po, int $approvedBy): PurchaseOrder
    {
        if (! $po->canBeConfirmed()) {
            throw ValidationException::withMessages([
                'status' => 'Purchase order cannot be confirmed.',
            ]);
        }

        $po->update([
            'status'      => PoStatus::Confirmed,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);

        return $po->refresh();
    }

    /**
     * Cancel a PO.
     */
    public function cancelPurchaseOrder(PurchaseOrder $po): PurchaseOrder
    {
        if (! $po->canBeCancelled()) {
            throw ValidationException::withMessages([
                'status' => 'This purchase order cannot be cancelled.',
            ]);
        }

        $po->update(['status' => PoStatus::Cancelled]);

        return $po->refresh();
    }

    /**
     * Update PO items and recalculate totals.
     */
    public function updatePurchaseOrder(PurchaseOrder $po, array $data): PurchaseOrder
    {
        if ($po->status !== PoStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Only draft purchase orders can be edited.',
            ]);
        }

        return DB::transaction(function () use ($po, $data) {
            $po->update([
                'supplier_id'            => $data['supplier_id'],
                'currency_id'            => $data['currency_id'],
                'exchange_rate'          => $data['exchange_rate'],
                'order_date'             => $data['order_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'notes'                  => $data['notes'] ?? null,
                'terms_and_conditions'   => $data['terms_and_conditions'] ?? null,
            ]);

            // Sync items
            $po->items()->delete();
            foreach ($data['items'] as $item) {
                $po->items()->create([
                    'product_id'         => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'supplier_price_cny' => $item['supplier_price_cny'],
                    'quantity'           => $item['quantity'],
                    'approx_weight_kg'   => isset($item['approx_weight_kg']) && $item['approx_weight_kg'] > 0 ? $item['approx_weight_kg'] : null,
                    'subtotal_cny'       => $item['supplier_price_cny'] * $item['quantity'],
                    'notes'              => $item['notes'] ?? null,
                ]);
            }

            $po->recalculateTotals();

            return $po->refresh();
        });
    }

    /**
     * Record price history when goods are received.
     */
    public function recordPriceHistory(PoItem $item, PurchaseOrder $po): void
    {
        ProductPriceHistory::create([
            'product_id'         => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'supplier_id'        => $po->supplier_id,
            'purchase_order_id'  => $po->id,
            'price_cny'          => $item->supplier_price_cny,
            'price_bdt'          => $item->supplier_price_cny * $po->exchange_rate,
            'exchange_rate'      => $po->exchange_rate,
            'qty'                => $item->quantity,
            'recorded_at'        => now()->toDateString(),
            'created_at'         => now(),
        ]);
    }
}
