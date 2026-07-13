<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StorefrontCartRecord;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StorefrontCart
{
    /**
     * Effective "unlimited" stock ceiling for pre-order lines. Pre-order
     * products may be ordered beyond current stock (the checkout collects an
     * online advance), so their available quantity is capped at this large
     * value rather than the real stock count.
     */
    public const PREORDER_STOCK_CEILING = 100000;

    public function __construct(protected Session $session) {}

    public function add(Company $company, Product $product, int $quantity = 1, ?ProductVariant $variant = null): void
    {
        $quantity = max(1, $quantity);
        $items = $this->raw($company);
        $key = $this->lineKey($product, $variant);
        $maxStock = $this->availableStock($product, $variant);
        $currentQuantity = (int) ($items[$key]['quantity'] ?? 0);
        $nextQuantity = min($maxStock, max($product->effectiveMoq(), $currentQuantity + $quantity));

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
            'quantity' => min($this->availableStock($product, $variant), max($product->effectiveMoq(), $quantity)),
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

        $this->cartRecord($company)?->update(['status' => StorefrontCartRecord::STATUS_CONVERTED]);
    }

    /**
     * Attach the checkout contact to the persisted cart record so
     * abandoned-cart reminders can reach the customer.
     */
    public function rememberContact(Company $company, string $phone, ?string $name = null): void
    {
        $this->cartRecord($company)?->update([
            'phone' => $phone,
            'customer_name' => $name,
        ]);
    }

    protected function cartRecord(Company $company): ?StorefrontCartRecord
    {
        if (! Schema::hasTable('storefront_cart_records')) {
            return null;
        }

        return StorefrontCartRecord::withoutGlobalScopes()
            ->where('company_id', $company->getKey())
            ->where('session_id', $this->cartToken())
            ->first();
    }

    /**
     * Stable per-visitor token for the persisted cart record. Session IDs
     * regenerate, so a dedicated token is kept in the session instead.
     */
    protected function cartToken(): string
    {
        $token = $this->session->get('storefront_cart_token');

        if (! is_string($token) || $token === '') {
            $token = (string) Str::uuid();
            $this->session->put('storefront_cart_token', $token);
        }

        return $token;
    }

    protected function persistCartRecord(Company $company, array $items): void
    {
        if (! Schema::hasTable('storefront_cart_records')) {
            return;
        }

        if ($items === []) {
            $this->cartRecord($company)?->delete();

            return;
        }

        StorefrontCartRecord::withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $company->getKey(), 'session_id' => $this->cartToken()],
            [
                'items' => array_values($items),
                'status' => StorefrontCartRecord::STATUS_ACTIVE,
                'reminded_at' => null,
            ],
        );
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

                // MOQ floor first, then stock cap wins if stock is below MOQ.
                $quantity = min(max((int) $item['quantity'], $product->effectiveMoq()), $maxStock);

                if ($quantity !== (int) $item['quantity']) {
                    $changed = true;
                }

                $unitPrice = $variant
                    ? $variant->effectiveSalePrice()
                    : $product->priceForQuantity($quantity);

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
        if ($variant) {
            return (int) $variant->stock;
        }

        // Pre-order products can be ordered beyond current stock; the
        // checkout collects an online advance payment for those lines.
        if ($product->is_preorder) {
            return max((int) $product->stock, self::PREORDER_STOCK_CEILING);
        }

        return (int) $product->stock;
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

        $this->persistCartRecord($company, $items);
    }

    protected function key(Company $company): string
    {
        return 'storefront_cart.'.$company->getKey();
    }
}
