<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

class StorefrontCart
{
    public function __construct(protected Session $session) {}

    public function add(Company $company, Product $product, int $quantity = 1, ?ProductVariant $variant = null): void
    {
        $quantity = max(1, $quantity);
        $items = $this->raw($company);
        $key = $this->lineKey($product, $variant);
        $maxStock = $this->availableStock($product, $variant);
        $currentQuantity = (int) ($items[$key]['quantity'] ?? 0);
        $nextQuantity = min($maxStock, $currentQuantity + $quantity);

        if ($nextQuantity < 1) {
            return;
        }

        $items[$key] = [
            'product_id' => $product->getKey(),
            'variant_id' => $variant?->getKey(),
            'quantity' => $nextQuantity,
        ];

        $this->put($company, $items);
    }

    public function update(Company $company, Product $product, int $quantity, ?ProductVariant $variant = null): void
    {
        $items = $this->raw($company);
        $key = $this->lineKey($product, $variant);

        if ($quantity < 1) {
            unset($items[$key]);
            $this->put($company, $items);

            return;
        }

        $items[$key] = [
            'product_id' => $product->getKey(),
            'variant_id' => $variant?->getKey(),
            'quantity' => min($this->availableStock($product, $variant), $quantity),
        ];

        $this->put($company, $items);
    }

    public function remove(Company $company, Product $product, ?ProductVariant $variant = null): void
    {
        $items = $this->raw($company);
        unset($items[$this->lineKey($product, $variant)]);
        $this->put($company, $items);
    }

    public function clear(Company $company): void
    {
        $this->session->forget($this->key($company));
    }

    public function items(Company $company): Collection
    {
        $items = $this->raw($company);

        if ($items === []) {
            return collect();
        }

        $products = Product::query()
            ->with(['category', 'activeVariants'])
            ->whereIn('id', collect($items)->pluck('product_id')->unique()->all())
            ->where('is_active', true)
            ->where('status', Product::STATUS_AVAILABLE)
            ->get()
            ->keyBy('id');

        $changed = false;

        $cartItems = collect($items)
            ->map(function (array $item) use ($products, &$changed): ?array {
                $product = $products->get($item['product_id']);

                if (! $product) {
                    $changed = true;

                    return null;
                }

                $variant = null;

                if (! empty($item['variant_id'])) {
                    $variant = $product->activeVariants->firstWhere('id', (int) $item['variant_id']);

                    if (! $variant) {
                        $changed = true;

                        return null;
                    }
                }

                $maxStock = $this->availableStock($product, $variant);

                if ($maxStock < 1) {
                    $changed = true;

                    return null;
                }

                $quantity = min((int) $item['quantity'], $maxStock);

                if ($quantity !== (int) $item['quantity']) {
                    $changed = true;
                }

                $unitPrice = $variant
                    ? $variant->effectiveSalePrice()
                    : (float) $product->selling_price;

                return [
                    'product' => $product,
                    'variant' => $variant,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $unitPrice * $quantity,
                ];
            })
            ->filter()
            ->values();

        if ($changed) {
            $this->put(
                $company,
                $cartItems
                    ->mapWithKeys(fn (array $item): array => [
                        $this->lineKey($item['product'], $item['variant']) => [
                            'product_id' => $item['product']->getKey(),
                            'variant_id' => $item['variant']?->getKey(),
                            'quantity' => $item['quantity'],
                        ],
                    ])
                    ->all(),
            );
        }

        return $cartItems;
    }

    public function count(Company $company): int
    {
        return $this->items($company)->sum('quantity');
    }

    public function subtotal(Company $company): float
    {
        return (float) $this->items($company)->sum('subtotal');
    }

    protected function availableStock(Product $product, ?ProductVariant $variant): int
    {
        return $variant ? (int) $variant->stock : (int) $product->stock;
    }

    protected function lineKey(Product $product, ?ProductVariant $variant): string
    {
        return $product->getKey().':'.($variant?->getKey() ?? 0);
    }

    protected function raw(Company $company): array
    {
        $items = $this->session->get($this->key($company), []);

        return is_array($items) ? $items : [];
    }

    protected function put(Company $company, array $items): void
    {
        $this->session->put($this->key($company), $items);
    }

    protected function key(Company $company): string
    {
        return 'storefront_cart.'.$company->getKey();
    }
}
