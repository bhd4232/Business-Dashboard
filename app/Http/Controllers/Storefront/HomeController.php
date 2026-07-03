<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCarousel;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $company = $request->attributes->get('storefront_company');

        if (! $company instanceof Company) {
            return view('marketing.home');
        }

        $setting = $company->storefrontSetting;

        abort_unless($setting?->is_published, 404);

        return view('storefront.home', [
            'company' => $company,
            'setting' => $setting,
            'categories' => Category::query()
                ->where('is_active', true)
                ->whereHas('products', fn ($query) => $query->where('is_active', true)->where('status', Product::STATUS_AVAILABLE))
                ->orderBy('name')
                ->take(8)
                ->get(),
            'products' => Product::query()
                ->with('category')
                ->where('is_active', true)
                ->where('status', Product::STATUS_AVAILABLE)
                ->latest()
                ->take(12)
                ->get(),
            'carousels' => ProductCarousel::forHomepage(),
        ]);
    }
}
