<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\OfferItem;

/**
 * Combo/single offer pricing + stock-explosion into plain OrderItem rows.
 * Component unit prices use the same accessors the storefront cart already
 * uses (Product::$selling_price / ProductVariant::effectiveSalePrice()) so
 * an offer's price always matches what the same product costs on its own
 * product page.
 */
class OfferPricingService
{
    /** Sum of the component products' own selling prices (auto_sum base, and the "was" reference price even in manual mode). */
    public function componentsSubtotal(Offer $offer): float
    {
        return $offer->items->sum(function (OfferItem $item): float {
            $unitPrice = $item->productVariant?->effectiveSalePrice() ?? $item->product->selling_price;

            return (float) $unitPrice * $item->quantity;
        });
    }

    /** Final selling price — price_mode + discount applied. */
    public function finalPrice(Offer $offer): float
    {
        $base = $offer->price_mode === Offer::PRICE_MODE_MANUAL && $offer->manual_price !== null
            ? (float) $offer->manual_price
            : $this->componentsSubtotal($offer);

        if ($offer->discount_type === Offer::DISCOUNT_PERCENT && $offer->discount_value) {
            $base -= $base * ((float) $offer->discount_value / 100);
        } elseif ($offer->discount_type === Offer::DISCOUNT_FLAT && $offer->discount_value) {
            $base -= (float) $offer->discount_value;
        }

        return max(0, round($base, 2));
    }

    /**
     * Explode a combo offer into plain OrderItem rows — one line per
     * component product, quantity = component_qty * ordered_qty. Price is
     * distributed proportionally to each component's normal price share of
     * the components subtotal, so per-product accounting/COGS stays
     * approximately correct even with a discount applied. This lets the
     * existing stock/accounting pipeline (OrderWorkflowService,
     * StockMovementService) handle combo orders unchanged — to them it's
     * just N ordinary OrderItem rows.
     *
     * @return array<int, array{product_id:int, product_variant_id:?int, variant_label:?string, quantity:int, unit_price:float, unit_cost:float}>
     */
    public function explodeToOrderLines(Offer $offer, int $orderedQuantity): array
    {
        $componentsSubtotal = $this->componentsSubtotal($offer) ?: 1; // divide-by-zero guard
        $finalPrice = $this->finalPrice($offer);

        return $offer->items->map(function (OfferItem $item) use ($orderedQuantity, $componentsSubtotal, $finalPrice): array {
            $variant = $item->productVariant;
            $product = $item->product;
            $unitSellingPrice = $variant?->effectiveSalePrice() ?? $product->selling_price;
            $componentLineNormal = (float) $unitSellingPrice * $item->quantity;
            $share = $componentLineNormal / $componentsSubtotal;
            $totalQty = $item->quantity * $orderedQuantity;

            return [
                'product_id' => $product->getKey(),
                'product_variant_id' => $variant?->getKey(),
                'variant_label' => $variant?->label(),
                'quantity' => $totalQty,
                // Revenue allocated to this component for ONE offer bundle is
                // ($finalPrice * $share); dividing by $item->quantity (not
                // $totalQty) gives the correct per-unit price — orderedQuantity
                // cancels out since totalQty already scales by it too.
                'unit_price' => round(($finalPrice * $share) / $item->quantity, 2),
                'unit_cost' => (float) ($variant?->effectiveCostPrice() ?? $product->cost_price ?? 0),
            ];
        })->all();
    }

    /** Assert enough real stock exists for every component product before checkout proceeds. */
    public function assertInStock(Offer $offer, int $orderedQuantity): void
    {
        foreach ($offer->items as $item) {
            $available = $item->productVariant?->stock ?? $item->product->stock;
            $needed = $item->quantity * $orderedQuantity;

            abort_if($needed > $available, 422, "{$item->product->name} does not have enough stock for this offer.");
        }
    }
}
