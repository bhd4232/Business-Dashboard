<?php

namespace App\Http\Controllers\Api\V1\Procurement;

use App\Http\Controllers\Concerns\HasTrash;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StoreProductRequest;
use App\Models\Core\Category;
use App\Models\Core\Product;
use App\Models\Core\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    use HasTrash;
    /**
     * Paginated product browser for the Create Order page.
     * No minimum query length — returns all active products when q is empty.
     * Supports ?q, ?price_tier_id, ?page, ?per_page (max 40).
     */
    public function browse(Request $request): JsonResponse
    {
        $q       = trim($request->get('q', ''));
        $tierId  = $request->get('price_tier_id');
        $perPage = min((int) $request->get('per_page', 20), 40);

        $products = Product::with([
            'variants' => fn ($vq) => $vq->where('is_active', true)
                ->select('id', 'product_id', 'variant_name', 'sku', 'weight_kg'),
        ])
            ->active()
            ->when($q, fn ($query) => $query->where(fn ($q2) =>
                $q2->where('name', 'like', "%{$q}%")
                   ->orWhere('sku',  'like', "%{$q}%")
                   ->orWhere('name_chinese', 'like', "%{$q}%")
            ))
            ->select('id', 'name', 'sku', 'name_chinese', 'weight_kg', 'has_variants', 'image')
            ->orderBy('name')
            ->paginate($perPage);

        // Attach tier-specific pricing
        if ($tierId) {
            $productIds = $products->pluck('id')->toArray();
            $tierPrices = DB::table('product_price_tiers')
                ->whereIn('product_id', $productIds)
                ->where('price_tier_id', $tierId)
                ->get(['product_id', 'product_variant_id', 'price_bdt']);

            $priceMap = [];
            foreach ($tierPrices as $tp) {
                $priceMap[$tp->product_id][$tp->product_variant_id ?? 'base'] = (float) $tp->price_bdt;
            }
            foreach ($products as $product) {
                $product->price_bdt = $priceMap[$product->id]['base'] ?? null;
                foreach ($product->variants as $variant) {
                    $variant->price_bdt = $priceMap[$product->id][$variant->id]
                        ?? $priceMap[$product->id]['base']
                        ?? null;
                }
            }
        }

        return response()->json($products);
    }

    /**
     * Returns max 15 results: id, name, sku, name_chinese, weight_kg, has_variants, variants.
     *
     * Optional: ?price_tier_id=N  — appends price_bdt from product_price_tiers for that tier.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim($request->get('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $tierId = $request->get('price_tier_id');

        $products = Product::with(['variants' => fn ($vq) => $vq->where('is_active', true)
                ->select('id', 'product_id', 'variant_name', 'sku', 'weight_kg')])
            ->active()
            ->where(fn ($query) =>
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('sku', 'like', "%{$q}%")
                      ->orWhere('name_chinese', 'like', "%{$q}%")
            )
            ->select('id', 'name', 'sku', 'name_chinese', 'weight_kg', 'has_variants')
            ->orderBy('name')
            ->limit(15)
            ->get();

        // Attach tier-specific pricing if price_tier_id is provided
        if ($tierId) {
            // Build a lookup: [product_id => [variant_id|null => price_bdt]]
            $productIds = $products->pluck('id')->toArray();
            $tierPrices = DB::table('product_price_tiers')
                ->whereIn('product_id', $productIds)
                ->where('price_tier_id', $tierId)
                ->get(['product_id', 'product_variant_id', 'price_bdt']);

            $priceMap = [];
            foreach ($tierPrices as $tp) {
                $priceMap[$tp->product_id][$tp->product_variant_id ?? 'base'] = (float) $tp->price_bdt;
            }

            foreach ($products as $product) {
                $product->price_bdt = $priceMap[$product->id]['base'] ?? null;

                foreach ($product->variants as $variant) {
                    $variant->price_bdt = $priceMap[$product->id][$variant->id]
                        ?? $priceMap[$product->id]['base']
                        ?? null;
                }
            }
        }

        return response()->json($products);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('products.view');

        $query = Product::with(['category', 'variants'])
            ->when($request->search, fn($q, $s) =>
                $q->where(fn($q) =>
                    $q->where('name', 'like', "%{$s}%")
                      ->orWhere('sku', 'like', "%{$s}%")
                      ->orWhere('name_chinese', 'like', "%{$s}%")
                ))
            ->when($request->category_id, fn($q, $c) => $q->where('category_id', $c))
            ->when($request->boolean('active_only', false), fn($q) => $q->active())
            ->orderByDesc('created_at');

        return response()->json($query->paginate(25));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = DB::transaction(function () use ($request) {
            $data = $request->safe()->except('variants');

            // Auto-generate SKU if not provided
            if (empty($data['sku'])) {
                $data['sku'] = $this->generateSku($data['category_id'], $data['name']);
            }

            $product = Product::create(array_merge($data, ['created_by' => auth()->id()]));

            if ($request->variants) {
                foreach ($request->variants as $v) {
                    if (empty($v['sku'])) {
                        $v['sku'] = $product->sku . '-' . Str::upper(Str::random(4));
                    }
                    $product->variants()->create($v);
                }
                $product->update(['has_variants' => true]);
            }

            return $product->load(['category', 'variants']);
        });

        return response()->json($product, 201);
    }

    public function show(Product $product): JsonResponse
    {
        $this->authorize('products.view');

        return response()->json(
            $product->load(['category', 'variants', 'barcodes'])
        );
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $this->authorize('products.edit');

        $data = $request->validate([
            'name'            => 'sometimes|string|max:255',
            'name_chinese'    => 'nullable|string|max:255',
            'category_id'     => 'sometimes|exists:categories,id',
            'unit'            => 'sometimes|string|max:20',
            'weight_kg'       => 'nullable|numeric|min:0',
            'volume_cm3'      => 'nullable|numeric|min:0',
            'description'     => 'nullable|string',
            'min_stock_alert' => 'nullable|integer|min:0',
            'regular_price'   => 'nullable|numeric|min:0',
            'selling_price'   => 'nullable|numeric|min:0',
            'is_active'       => 'nullable|boolean',
        ]);

        $product->update($data);

        return response()->json($product->fresh(['category', 'variants']));
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('products.delete');
        return $this->softDelete($product, 'Product');
    }

    public function trashed(Request $request): JsonResponse
    {
        $this->authorize('products.view');
        return response()->json(Product::onlyTrashed()->with('category')->orderByDesc('deleted_at')->get());
    }

    public function restore(int $id): JsonResponse
    {
        $this->authorize('products.edit');
        return $this->restoreModel(Product::onlyTrashed()->findOrFail($id), 'Product');
    }

    public function forceDelete(int $id): JsonResponse
    {
        return $this->purgeModel(Product::onlyTrashed()->findOrFail($id), 'Product');
    }

    public function storeVariant(Request $request, Product $product): JsonResponse
    {
        $this->authorize('products.create');

        $data = $request->validate([
            'variant_name' => 'required|string|max:255',
            'sku'          => 'nullable|string|max:100|unique:product_variants,sku',
            'weight_kg'    => 'nullable|numeric|min:0',
            'attributes'   => 'nullable|array',
        ]);

        if (empty($data['sku'])) {
            $data['sku'] = $product->sku . '-' . Str::upper(Str::random(4));
        }

        $variant = $product->variants()->create($data);
        $product->update(['has_variants' => true]);

        return response()->json($variant, 201);
    }

    public function destroyVariant(Product $product, ProductVariant $variant): JsonResponse
    {
        $this->authorize('products.delete');
        $variant->update(['is_active' => false]);

        return response()->json(['message' => 'Variant deactivated.']);
    }

    public function categories(): JsonResponse
    {
        $this->authorize('categories.view');

        return response()->json(
            Category::active()->with('children')->root()->orderBy('sort_order')->get()
        );
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $this->authorize('categories.create');

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'parent_id'   => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'sort_order'  => 'nullable|integer',
        ]);

        $data['slug'] = Str::slug($data['name']) . '-' . Str::random(4);

        return response()->json(Category::create($data), 201);
    }

    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $this->authorize('categories.edit');
        $category = Category::findOrFail($id);
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'parent_id'  => 'nullable|exists:categories,id',
            'sort_order' => 'nullable|integer',
        ]);
        $data['slug'] = \Illuminate\Support\Str::slug($data['name']) . '-' . strtolower(substr($category->slug, -4));
        $category->update($data);
        return response()->json($category);
    }

    public function destroyCategory(int $id): JsonResponse
    {
        $this->authorize('categories.edit');
        $category = Category::findOrFail($id);
        if ($category->products()->count() > 0) {
            return response()->json(['message' => 'Cannot delete a category that has products.'], 422);
        }
        $category->delete();
        return response()->json(['message' => 'Category moved to trash.']);
    }

    public function trashedCategories(): JsonResponse
    {
        $this->authorize('categories.view');
        return response()->json(Category::onlyTrashed()->orderByDesc('deleted_at')->get());
    }

    public function restoreCategory(int $id): JsonResponse
    {
        $this->authorize('categories.edit');
        $category = Category::onlyTrashed()->findOrFail($id);
        $category->restore();
        return response()->json(['message' => 'Category restored.']);
    }

    private function generateSku(int $categoryId, string $productName): string
    {
        $category = Category::find($categoryId);
        $code = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $category?->name ?? 'GEN'), 0, 4));
        if (! $code) {
            $code = 'PROD';
        }

        $count = Product::where('sku', 'like', "ZAM-{$code}-%")->count() + 1;

        return 'ZAM-' . $code . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
