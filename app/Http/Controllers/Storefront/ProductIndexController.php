<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ResellerProduct;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductIndexController extends Controller
{
    public function __invoke(Request $request): View
    {
        $company = $request->attributes->get('storefront_company');

        abort_unless($company instanceof Company && $company->storefrontSetting?->is_published, 404);

        $reseller = $request->attributes->get('storefront_reseller');

        // Read the `{slug}` route parameter explicitly by name rather than
        // via method-injection: this controller is invoked from routes with
        // different parameter counts (bare /products, /category/{slug}, and
        // the reseller-store /store/{resellerSlug}/... mirrors of both), and
        // Laravel's implicit controller-call resolution binds scalar route
        // parameters by position, not name -- an extra leading URI segment
        // like {resellerSlug} would otherwise land in $slug by accident.
        $slug = $request->route('slug');

        $category = $slug
            ? Category::query()->where('slug', $slug)->where('is_active', true)->firstOrFail()
            : null;

        $sort = $request->string('sort')->value();
        $search = trim((string) $request->string('q'));

        $products = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->where('status', Product::STATUS_AVAILABLE)
            ->when(
                $reseller instanceof Customer,
                fn ($query) => $query->whereIn('id', ResellerProduct::query()
                    ->where('customer_id', $reseller->getKey())
                    ->where('is_active', true)
                    ->select('product_id')),
            )
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
