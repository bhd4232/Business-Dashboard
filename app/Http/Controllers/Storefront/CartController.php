<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
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

        return $this->addToCart($request, $company, $product);
    }

    public function addPreview(Request $request, Company $company, string $slug): RedirectResponse
    {
        $this->previewStorefront($company);
        $product = $this->product($slug);

        return $this->addToCart($request, $company, $product);
    }

    /**
     * Handles both simple products (single quantity) and variable products,
     * where multiple variants with individual quantities are added in one
     * submit via quantities[variant_id] inputs.
     */
    protected function addToCart(Request $request, Company $company, Product $product): RedirectResponse
    {
        $buyNow = $request->boolean('buy_now');

        if (! $product->has_variants) {
            $this->cart->add($company, $product, (int) $request->integer('quantity', 1));

            if ($buyNow) {
                return $this->redirectToCheckout($request, $company);
            }

            return back()->with('storefront_status', "{$product->name} added to cart.");
        }

        $quantities = collect((array) $request->input('quantities', []))
            ->map(fn ($quantity): int => max(0, (int) $quantity))
            ->filter(fn (int $quantity): bool => $quantity > 0);

        // Backwards-compatible: single variant + quantity inputs.
        if ($quantities->isEmpty() && $request->filled('variant')) {
            $quantities = collect([(int) $request->integer('variant') => max(1, (int) $request->integer('quantity', 1))]);
        }

        abort_if($quantities->isEmpty(), 422, 'Please select quantity for at least one option.');

        $variants = ProductVariant::query()
            ->where('product_id', $product->getKey())
            ->where('is_active', true)
            ->whereIn('id', $quantities->keys()->all())
            ->get()
            ->keyBy('id');

        abort_if($variants->isEmpty(), 422, 'Selected options are not available.');

        $addedLines = 0;

        foreach ($quantities as $variantId => $quantity) {
            $variant = $variants->get((int) $variantId);

            if (! $variant || (int) $variant->stock < 1) {
                continue;
            }

            $this->cart->add($company, $product, $quantity, $variant);
            $addedLines++;
        }

        abort_if($addedLines === 0, 422, 'Selected options are out of stock.');

        if ($buyNow) {
            return $this->redirectToCheckout($request, $company);
        }

        return back()->with('storefront_status', "{$product->name} added to cart ({$addedLines} ".($addedLines === 1 ? 'option' : 'options').').');
    }

    protected function redirectToCheckout(Request $request, Company $company): RedirectResponse
    {
        $previewSlug = $request->route('company')?->slug;

        return redirect()->to($previewSlug
            ? route('storefront.preview.checkout.show', $previewSlug)
            : route('storefront.checkout.show'));
    }

    public function update(Request $request, string $slug): RedirectResponse
    {
        [$company] = $this->domainStorefront($request);
        $product = $this->product($slug);

        $this->cart->update($company, $product, (int) $request->integer('quantity'), $this->resolveVariant($request, $product));

        return back()->with('storefront_status', 'Cart updated.');
    }

    public function updatePreview(Request $request, Company $company, string $slug): RedirectResponse
    {
        $this->previewStorefront($company);
        $product = $this->product($slug);

        $this->cart->update($company, $product, (int) $request->integer('quantity'), $this->resolveVariant($request, $product));

        return back()->with('storefront_status', 'Cart updated.');
    }

    public function remove(Request $request, string $slug): RedirectResponse
    {
        [$company] = $this->domainStorefront($request);
        $product = $this->product($slug);
        $this->cart->remove($company, $product, $this->resolveVariant($request, $product));

        return back()->with('storefront_status', 'Item removed from cart.');
    }

    public function removePreview(Request $request, Company $company, string $slug): RedirectResponse
    {
        $this->previewStorefront($company);
        $product = $this->product($slug);
        $this->cart->remove($company, $product, $this->resolveVariant($request, $product));

        return back()->with('storefront_status', 'Item removed from cart.');
    }

    protected function resolveVariant(Request $request, Product $product, bool $requireForVariable = false): ?ProductVariant
    {
        $variantId = (int) $request->integer('variant');

        if ($variantId < 1) {
            abort_if($requireForVariable && $product->has_variants, 422, 'Please select an option first.');

            return null;
        }

        $variant = ProductVariant::query()
            ->where('product_id', $product->getKey())
            ->where('is_active', true)
            ->find($variantId);

        abort_unless($variant, 404);

        return $variant;
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
            ->where(fn ($query) => $query
                ->where('stock', '>', 0)
                ->orWhere('is_preorder', true))
            ->firstOrFail();
    }
}
