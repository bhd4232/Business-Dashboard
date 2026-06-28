<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

class StorefrontCart
{
    public function __construct(protected Session $session) {}

    public function add(Company $company, Product $product, int $quantity = 1): void
    {
        $quantity = max(1, $quantity);
        $items = $this->raw($company);
        $currentQuantity = (int) ($items[$product->getKey()]['quantity'] ?? 0);
        $nextQuantity = min($product->stock, $currentQuantity + $quantity);

        if ($nextQuantity < 1) {
            return;
        }

        $items[$product->getKey()] = [
            'product_id' => $product->getKey(),
            'quantity' => $nextQuantity,
        ];

        $this->put($company, $items);
    }

    public function update(Company $company, Product $product, int $quantity): void
    {
        $items = $this->raw($company);

        if ($quantity < 1) {
            unset($items[$product->getKey()]);
            $this->put($company, $items);

            return;
        }

        $items[$product->getKey()] = [
            'product_id' => $product->getKey(),
            'quantity' => min($product->stock, $quantity),
        ];

        $this->put($company, $items);
    }

    public function remove(Company $company, Product $product): void
    {
        $items = $this->raw($company);
        unset($items[$product->getKey()]);
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
            ->with('category')
            ->whereIn('id', collect($items)->pluck('product_id')->all())
            ->where('is_active', true)
            ->where('status', Product::STATUS_AVAILABLE)
            ->get()
            ->keyBy('id');

        $changed = false;

        $cartItems = collect($items)
            ->map(function (array $item) use ($products, &$changed): ?array {
                $product = $products->get($item['product_id']);

                if (! $product || $product->stock < 1) {
                    $changed = true;

                    return null;
                }

                $quantity = min((int) $item['quantity'], (int) $product->stock);

                if ($quantity !== (int) $item['quantity']) {
                    $changed = true;
                }

                return [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => (float) $product->selling_price,
                    'subtotal' => (float) $product->selling_price * $quantity,
                ];
            })
            ->filter()
            ->values();

        if ($changed) {
            $this->put(
                $company,
                $cartItems
                    ->mapWithKeys(fn (array $item): array => [
                        $item['product']->getKey() => [
                            'product_id' => $item['product']->getKey(),
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
