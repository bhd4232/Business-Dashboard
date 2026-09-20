<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CompanyApiKey;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Product/stock two-way sync for the external website integration.
 * CompanyContext is already set by ResolveCompanyFromApiKey, so every query
 * below is naturally scoped to the calling company via CompanyScope.
 */
class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->paginate(min((int) $request->integer('per_page', 50), 200));

        return response()->json([
            'data' => $products->getCollection()->map($this->transform(...))->all(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function show(string $sku): JsonResponse
    {
        $product = Product::query()->where('sku', $sku)->firstOrFail();

        return response()->json(['data' => $this->transform($product)]);
    }

    /**
     * Price/sale_price are plain fields, written directly. Stock is never
     * overwritten directly — it goes through Product::setStockFromProductForm(),
     * which records the change as a StockMovement (ledger-safe, same as an
     * admin editing stock from the Product form), stamped with this API key
     * as its reference so StockMovement::booted() knows not to echo a
     * webhook back to the same website that just made this change.
     */
    public function update(Request $request, string $sku): JsonResponse
    {
        $product = Product::query()->where('sku', $sku)->firstOrFail();

        $data = $request->validate([
            'price' => ['sometimes', 'numeric', 'min:0'],
            'sale_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'stock' => ['sometimes', 'integer', 'min:0'],
        ]);

        abort_if($data === [], 422, 'At least one of price, sale_price, or stock is required.');

        if (Arr::hasAny($data, ['price', 'sale_price'])) {
            $product->suppressWebsiteWebhook = true;
            $product->fill(Arr::only($data, ['price', 'sale_price']));
            $product->save();
        }

        if (array_key_exists('stock', $data)) {
            /** @var CompanyApiKey $apiKey */
            $apiKey = $request->attributes->get('company_api_key');

            $product->setStockFromProductForm(
                (int) $data['stock'],
                referenceType: CompanyApiKey::class,
                referenceId: $apiKey->getKey(),
                reason: 'Website API sync',
                note: 'Stock synced from website API',
            );
        }

        return response()->json(['data' => $this->transform($product->refresh())]);
    }

    protected function transform(Product $product): array
    {
        return [
            'sku' => $product->sku,
            'name' => $product->name,
            'price' => (float) $product->price,
            'sale_price' => $product->sale_price !== null ? (float) $product->sale_price : null,
            'stock' => (int) $product->stock,
            'is_active' => (bool) $product->is_active,
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];
    }
}
