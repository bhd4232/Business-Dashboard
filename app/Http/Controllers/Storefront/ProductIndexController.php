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

        $products = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->where('status', Product::STATUS_AVAILABLE)
            ->when($category, fn ($query) => $query->whereBelongsTo($category))
            ->latest()
            ->paginate(24)
            ->withQueryString();

        return view('storefront.products.index', [
            'company' => $company,
            'setting' => $company->storefrontSetting,
            'category' => $category,
            'products' => $products,
        ]);
    }
}
