<?php

namespace App\Services\Shipping;

use App\Enums\CostAllocationMethod;
use App\Models\Shipping\LandingCostAllocation;
use App\Models\Shipping\Shipment;
use Illuminate\Support\Collection;

class LandingCostService
{
    /**
     * Calculate landing cost for all items in a shipment.
     * Returns an array of allocation data (does NOT persist — call save() separately).
     */
    public function calculate(Shipment $shipment): Collection
    {
        $shipment->loadMissing([
            'items.poItem.purchaseOrder',
            'items.product',
            'costs',
        ]);

        $method      = $shipment->cost_allocation_method ?? CostAllocationMethod::Weight;
        $items       = $shipment->items;
        $costsByType = $shipment->costs->groupBy(fn($c) => $c->cost_type->value)->map->sum('amount_bdt');

        // Group costs into landing-cost buckets
        $freight   = (float) ($costsByType['freight']    ?? 0);
        $duty      = (float) ($costsByType['customs_duty'] ?? 0);
        $vat       = (float) ($costsByType['vat']         ?? 0);
        $ait       = (float) ($costsByType['ait']         ?? 0);
        $labour    = (float) ($costsByType['labour']      ?? 0);
        $transport = (float) ($costsByType['transport']   ?? 0);
        $customsFee = (float) ($costsByType['customs_fee'] ?? 0);
        $demurrage = (float) ($costsByType['demurrage']   ?? 0);
        $other     = (float) ($costsByType['other']       ?? 0) + $customsFee + $demurrage;

        // Calculate allocation denominators
        $totalWeight = max((float) $items->sum('weight_kg'), 0.001);
        $totalVolume = max((float) $items->sum('volume_cm3'), 0.001);
        $totalQty    = max((int)   $items->sum('quantity'), 1);

        // Purchase value denominator (needs PO exchange rate)
        $totalValue = $items->reduce(function ($carry, $item) {
            if ($item->poItem) {
                return $carry + ($item->quantity * $item->poItem->supplier_price_cny * $item->poItem->purchaseOrder->exchange_rate);
            }
            return $carry;
        }, 0.0);
        $totalValue = max($totalValue, 0.01);

        return $items->map(function ($item) use (
            $method, $totalWeight, $totalVolume, $totalQty, $totalValue,
            $freight, $duty, $vat, $ait, $labour, $transport, $other
        ) {
            // Purchase cost in BDT
            $purchaseCostBdt = 0.0;
            $priceCny        = 0.0;

            if ($item->poItem) {
                $priceCny        = (float) $item->poItem->supplier_price_cny;
                $exRate          = (float) $item->poItem->purchaseOrder->exchange_rate;
                $purchaseCostBdt = $item->quantity * $priceCny * $exRate;
            }

            // Allocation ratio
            $ratio = match($method) {
                CostAllocationMethod::Volume   => (float)($item->volume_cm3 ?? 0) / $totalVolume,
                CostAllocationMethod::Value    => $purchaseCostBdt / $totalValue,
                CostAllocationMethod::Quantity => $item->quantity / $totalQty,
                default                        => (float)($item->weight_kg ?? 0) / $totalWeight,
            };

            $alloc = [
                'freight'   => $freight   * $ratio,
                'duty'      => $duty      * $ratio,
                'vat'       => $vat       * $ratio,
                'ait'       => $ait       * $ratio,
                'labour'    => $labour    * $ratio,
                'transport' => $transport * $ratio,
                'other'     => $other     * $ratio,
            ];

            $totalLanding = $purchaseCostBdt + array_sum($alloc);
            $qty          = max($item->quantity, 1);

            return [
                'shipment_id'               => $item->shipment_id,
                'po_item_id'                => $item->po_item_id,
                'product_id'                => $item->product_id,
                'product_variant_id'        => $item->product_variant_id,
                'quantity'                  => $qty,
                'weight_kg'                 => $item->weight_kg,
                'volume_cm3'                => $item->volume_cm3,
                'purchase_price_cny'        => $priceCny,
                'purchase_price_bdt'        => round($purchaseCostBdt, 2),
                'allocated_freight_bdt'     => round($alloc['freight'],   4),
                'allocated_customs_bdt'     => round($alloc['duty'],      4),
                'allocated_vat_bdt'         => round($alloc['vat'],       4),
                'allocated_ait_bdt'         => round($alloc['ait'],       4),
                'allocated_labour_bdt'      => round($alloc['labour'],    4),
                'allocated_transport_bdt'   => round($alloc['transport'], 4),
                'allocated_other_bdt'       => round($alloc['other'],     4),
                'total_landing_cost_bdt'    => round($totalLanding,       4),
                'landing_cost_per_unit_bdt' => round($totalLanding / $qty, 4),
                // Extra: product name for display
                '_product_name'             => $item->product?->name,
                '_sku'                      => $item->product?->sku,
            ];
        });
    }

    /**
     * Persist (upsert) landing cost allocations for a shipment.
     */
    public function saveAllocations(Shipment $shipment): void
    {
        $allocations = $this->calculate($shipment);

        // Remove display-only keys and upsert
        LandingCostAllocation::where('shipment_id', $shipment->id)->delete();

        foreach ($allocations as $alloc) {
            $alloc = collect($alloc)->except(['_product_name', '_sku'])->toArray();
            LandingCostAllocation::create($alloc);
        }
    }
}
