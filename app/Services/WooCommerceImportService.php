<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\StorefrontSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * One-time (re-runnable) product import from a WooCommerce site's REST API
 * into the company-scoped ERP Product/Category tables.
 *
 * Products are matched by SKU first (falling back to slug) so re-running the
 * import updates instead of duplicating. Stock is intentionally NOT imported:
 * ERP stock must come from stock movements, so imported products start at 0
 * stock unless they already exist.
 */
class WooCommerceImportService
{
    public function __construct(protected CompanyContext $context) {}

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importProducts(Company $company, bool $downloadImages = true, ?callable $progress = null): array
    {
        $setting = $company->storefrontSetting;

        if (! $setting instanceof StorefrontSetting || blank($setting->woocommerce_base_url)) {
            throw new RuntimeException('WooCommerce base URL is not configured in this company\'s storefront settings.');
        }

        $credentials = $setting->woocommerce_credentials ?? [];
        $key = (string) ($credentials['consumer_key'] ?? '');
        $secret = (string) ($credentials['consumer_secret'] ?? '');

        if ($key === '' || $secret === '') {
            throw new RuntimeException('WooCommerce consumer key/secret are not configured in this company\'s storefront settings.');
        }

        $this->context->set($company);

        $baseUrl = rtrim($setting->woocommerce_base_url, '/');
        $page = 1;
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        do {
            $response = Http::timeout(30)
                ->retry(2, 1000)
                ->withBasicAuth($key, $secret)
                ->get("{$baseUrl}/wp-json/wc/v3/products", [
                    'per_page' => 50,
                    'page' => $page,
                    'status' => 'publish',
                ]);

            if ($response->failed()) {
                throw new RuntimeException("WooCommerce API request failed (HTTP {$response->status()}) on page {$page}.");
            }

            $products = $response->json();

            if (! is_array($products)) {
                throw new RuntimeException('WooCommerce API returned an unexpected payload.');
            }

            foreach ($products as $payload) {
                $outcome = $this->importProduct($company, (array) $payload, $downloadImages);
                $result[$outcome]++;

                if ($progress) {
                    $progress($outcome, (string) ($payload['name'] ?? ''));
                }
            }

            $page++;
        } while (count($products) === 50);

        return $result;
    }

    protected function importProduct(Company $company, array $payload, bool $downloadImages): string
    {
        $name = trim((string) ($payload['name'] ?? ''));

        if ($name === '') {
            return 'skipped';
        }

        $sku = trim((string) ($payload['sku'] ?? ''));
        $slug = Str::slug((string) ($payload['slug'] ?? $name));

        $product = null;

        if ($sku !== '') {
            $product = Product::query()->where('sku', $sku)->first();
        }

        $product ??= Product::query()->where('slug', $slug)->first();

        $regularPrice = $this->toPrice($payload['regular_price'] ?? null) ?? $this->toPrice($payload['price'] ?? null) ?? 0.0;
        $salePrice = $this->toPrice($payload['sale_price'] ?? null) ?? $regularPrice;

        $attributes = [
            'name' => $name,
            'description' => trim(strip_tags((string) (($payload['short_description'] ?? '') ?: ($payload['description'] ?? '')))) ?: null,
            'price' => $regularPrice,
            'sale_price' => $salePrice,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
            'category_id' => $this->resolveCategory($payload['categories'] ?? [])?->getKey(),
        ];

        $isNew = ! $product;

        if ($isNew) {
            $product = new Product([
                ...$attributes,
                'sku' => $sku !== '' ? $sku : 'WOO-'.Str::upper(Str::random(8)),
                'slug' => $slug,
                'unit' => 'pcs',
                'cost_price' => 0,
                'stock' => 0,
                'reorder_level' => 0,
                'vat_rate' => 0,
            ]);
            $product->company_id = $company->getKey();
        } else {
            $product->fill($attributes);
        }

        if ($downloadImages && blank($product->image)) {
            $imageUrl = (string) (data_get($payload, 'images.0.src') ?? '');

            if ($imageUrl !== '') {
                $product->image = $this->downloadImage($imageUrl) ?? $product->image;
            }
        }

        $product->save();

        return $isNew ? 'created' : 'updated';
    }

    protected function resolveCategory(array $categories): ?Category
    {
        $name = trim((string) (data_get($categories, '0.name') ?? ''));

        if ($name === '') {
            return null;
        }

        $existing = Category::query()->where('name', $name)->first();

        if ($existing) {
            return $existing;
        }

        $slug = Str::slug($name) ?: 'category';
        $unique = $slug;
        $suffix = 2;

        while (Category::query()->where('slug', $unique)->exists()) {
            $unique = "{$slug}-{$suffix}";
            $suffix++;
        }

        return Category::query()->create([
            'name' => $name,
            'slug' => $unique,
            'is_active' => true,
        ]);
    }

    protected function downloadImage(string $url): ?string
    {
        try {
            $response = Http::timeout(20)->get($url);

            if ($response->failed()) {
                return null;
            }

            $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION)) ?: 'jpg';

            if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                $extension = 'jpg';
            }

            $path = 'products/woo-'.Str::random(20).'.'.$extension;
            Storage::disk('public')->put($path, $response->body());

            return $path;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function toPrice(mixed $value): ?float
    {
        return is_numeric($value) && (float) $value >= 0 ? (float) $value : null;
    }
}
