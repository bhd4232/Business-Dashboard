<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ResellerProduct;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductShowController extends Controller
{
    public function __invoke(Request $request): View
    {
        $company = $request->attributes->get('storefront_company');

        abort_unless($company instanceof Company && $company->storefrontSetting?->is_published, 404);

        $reseller = $request->attributes->get('storefront_reseller');

        // See ProductIndexController::__invoke() for why $slug is read by
        // name rather than method-injected: this controller is also mounted
        // under /store/{resellerSlug}/product/{slug}, and Laravel resolves
        // unmatched scalar route parameters positionally, not by name.
        $slug = (string) $request->route('slug');

        $product = Product::query()
            ->with('category')
            ->where('slug', $slug)
            ->where('is_active', true)
            ->where('status', Product::STATUS_AVAILABLE)
            ->when(
                $reseller instanceof Customer,
                fn ($query) => $query->whereIn('id', ResellerProduct::query()
                    ->where('customer_id', $reseller->getKey())
                    ->where('is_active', true)
                    ->select('product_id')),
            )
            ->firstOrFail();

        $related = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->where('status', Product::STATUS_AVAILABLE)
            ->where('id', '!=', $product->id)
            ->when($product->category_id, fn ($query) => $query->where('category_id', $product->category_id))
            ->when(
                $reseller instanceof Customer,
                fn ($query) => $query->whereIn('id', ResellerProduct::query()
                    ->where('customer_id', $reseller->getKey())
                    ->where('is_active', true)
                    ->select('product_id')),
            )
            ->latest()
            ->limit(4)
            ->get();

        return view('storefront.products.show', [
            'company' => $company,
            'setting' => $company->storefrontSetting,
            'product' => $product,
            'related' => $related,
        ]);
    }
}
