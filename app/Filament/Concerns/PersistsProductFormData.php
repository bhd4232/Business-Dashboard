<?php

namespace App\Filament\Concerns;

use App\Models\Product;

/**
 * Shared write path for the product form, used by both the full-page
 * EditProduct and the Products list "Quick Edit" slide-over action so the two
 * can never drift apart.
 *
 * Two things the raw form payload must not be saved with directly:
 * - `price` is kept mirrored to `sale_price` (the form only edits sale_price).
 * - `stock` is routed through Product::setStockFromProductForm(), which records
 *   a proper opening/adjustment StockMovement — writing the `stock` column
 *   straight from an ->update() would desync the movement ledger, and the next
 *   syncProductStock() would overwrite it.
 */
trait PersistsProductFormData
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function mutateProductDataBeforeFill(array $data): array
    {
        $data['sale_price'] ??= $data['price'] ?? null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function mutateProductDataBeforeSave(array $data): array
    {
        if (array_key_exists('sale_price', $data)) {
            $data['price'] = $data['sale_price'];
        }

        return $data;
    }

    /**
     * Applies a validated product-form payload: mirrors price, persists every
     * column except `stock`, then reconciles stock through the ledger-safe
     * path. Relationship components (the variants repeater) are already saved
     * by the Filament schema before this runs.
     *
     * @param  array<string, mixed>  $data
     */
    protected static function saveProductFromForm(Product $record, array $data): Product
    {
        $data = static::mutateProductDataBeforeSave($data);

        $requestedStock = array_key_exists('stock', $data) ? (int) $data['stock'] : null;
        unset($data['stock']);

        $record->update($data);

        if ($requestedStock !== null) {
            $record->setStockFromProductForm($requestedStock);
        }

        return $record;
    }
}
