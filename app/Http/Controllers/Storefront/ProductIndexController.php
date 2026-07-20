<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductIndexController extends Controller
{
    public function __invoke(Request $request, ?string $slug = null): View
    {
        $company = $request->attributes->get('storefront_company');

        abort_unless($company instanceof Company && $company->storefrontSetting?->is_published, 404);

        $category = $slug
            ? Category::query()->where('slug', $slug)->where('is_active', true)->firstOrFail()
            : null;

        $sort = $request->string('sort')->value();
        $search = trim((string) $request->string('q'));

        $products = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->where('status', Product::STATUS_AVAILABLE)
            ->when($category, fn ($query) => $query->whereBelongsTo($category))
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")))
            ->when($sort === 'price_asc', fn ($query) => $query->orderByRaw('COALESCE(sale_price, price) asc'))
            ->when($sort === 'price_desc', fn ($query) => $query->orderByRaw('COALESCE(sale_price, price) desc'))
            ->when(! in_array($sort, ['price_asc', 'price_desc'], true), fn ($query) => $query->latest())
            ->paginate(24)
            ->withQueryString();

        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('storefront.products.index', [
            'company' => $company,
            'setting' => $company->storefrontSetting,
            'category' => $category,
            'categories' => $categories,
            'products' => $products,
            'sort' => $sort,
            'search' => $search,
        ]);
    }
}
