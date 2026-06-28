<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductShowController extends Controller
{
    public function __invoke(Request $request, string $slug): View
    {
        $company = $request->attributes->get('storefront_company');

        abort_unless($company instanceof Company && $company->storefrontSetting?->is_published, 404);

        $product = Product::query()
            ->with('category')
            ->where('slug', $slug)
            ->where('is_active', true)
            ->where('status', Product::STATUS_AVAILABLE)
            ->firstOrFail();

        return view('storefront.products.show', [
            'company' => $company,
            'setting' => $company->storefrontSetting,
            'product' => $product,
        ]);
    }
}
