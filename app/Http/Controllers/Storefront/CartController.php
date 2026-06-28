<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Product;
use App\Models\StorefrontSetting;
use App\Services\CompanyContext;
use App\Services\StorefrontCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(
        protected CompanyContext $context,
        protected StorefrontCart $cart,
    ) {}

    public function show(Request $request): View
    {
        [$company, $setting] = $this->domainStorefront($request);

        return $this->cartView($company, $setting);
    }

    public function showPreview(Company $company): View
    {
        $setting = $this->previewStorefront($company);

        return $this->cartView($company, $setting, $company->slug);
    }

    public function add(Request $request, string $slug): RedirectResponse
    {
        [$company] = $this->domainStorefront($request);
        $product = $this->product($slug);

        $this->cart->add($company, $product, (int) $request->integer('quantity', 1));

        return back()->with('storefront_status', "{$product->name} added to cart.");
    }

    public function addPreview(Request $request, Company $company, string $slug): RedirectResponse
    {
        $this->previewStorefront($company);
        $product = $this->product($slug);

        $this->cart->add($company, $product, (int) $request->integer('quantity', 1));

        return back()->with('storefront_status', "{$product->name} added to cart.");
    }

    public function update(Request $request, string $slug): RedirectResponse
    {
        [$company] = $this->domainStorefront($request);
        $product = $this->product($slug);

        $this->cart->update($company, $product, (int) $request->integer('quantity'));

        return back()->with('storefront_status', 'Cart updated.');
    }

    public function updatePreview(Request $request, Company $company, string $slug): RedirectResponse
    {
        $this->previewStorefront($company);
        $product = $this->product($slug);

        $this->cart->update($company, $product, (int) $request->integer('quantity'));

        return back()->with('storefront_status', 'Cart updated.');
    }

    public function remove(Request $request, string $slug): RedirectResponse
    {
        [$company] = $this->domainStorefront($request);
        $this->cart->remove($company, $this->product($slug));

        return back()->with('storefront_status', 'Item removed from cart.');
    }

    public function removePreview(Company $company, string $slug): RedirectResponse
    {
        $this->previewStorefront($company);
        $this->cart->remove($company, $this->product($slug));

        return back()->with('storefront_status', 'Item removed from cart.');
    }

    protected function cartView(Company $company, StorefrontSetting $setting, ?string $previewSlug = null): View
    {
        return view('storefront.cart.show', [
            'company' => $company,
            'setting' => $setting,
            'previewSlug' => $previewSlug,
            'items' => $this->cart->items($company),
            'subtotal' => $this->cart->subtotal($company),
        ]);
    }

    protected function domainStorefront(Request $request): array
    {
        $company = $request->attributes->get('storefront_company');

        abort_unless($company instanceof Company && $company->storefrontSetting?->is_published, 404);

        $this->context->set($company);

        return [$company, $company->storefrontSetting];
    }

    protected function previewStorefront(Company $company): StorefrontSetting
    {
        abort_unless(app()->environment(['local', 'testing']) || auth()->check(), 404);

        $this->context->set($company);

        return $company->storefrontSetting ?: new StorefrontSetting([
            'company_id' => $company->getKey(),
            'theme_color' => '#F59E0B',
            'meta_title' => $company->name,
            'is_published' => true,
        ]);
    }

    protected function product(string $slug): Product
    {
        return Product::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->where('status', Product::STATUS_AVAILABLE)
            ->where('stock', '>', 0)
            ->firstOrFail();
    }
}
