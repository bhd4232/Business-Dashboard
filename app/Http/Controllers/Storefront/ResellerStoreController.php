<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ResellerProduct;
use App\Services\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Self-service curation of an approved reseller's own storefront -- which
 * products show at {company-domain}/store/{reseller_slug}, and the slug
 * itself. Reuses the existing storefront Customer login (guard "customer"),
 * same as the rest of /account/*; no separate auth system.
 */
class ResellerStoreController extends Controller
{
    public function __construct(protected CompanyContext $context) {}

    public function editPreview(Request $request, Company $company): View|RedirectResponse
    {
        $this->preparePreview($request, $company);

        return $this->edit($request);
    }

    public function updateSlugPreview(Request $request, Company $company): RedirectResponse
    {
        $this->preparePreview($request, $company);

        return $this->updateSlug($request);
    }

    public function toggleProductPreview(Request $request, Company $company, Product $product): RedirectResponse
    {
        $this->preparePreview($request, $company);

        return $this->toggleProduct($request, $product);
    }

    public function edit(Request $request): View|RedirectResponse
    {
        $company = $this->domainStorefront($request);
        $reseller = $this->approvedReseller($request);

        if ($reseller instanceof RedirectResponse) {
            return $reseller;
        }

        $search = trim((string) $request->query('search', ''));

        $products = Product::query()
            ->where('is_active', true)
            ->where('status', Product::STATUS_AVAILABLE)
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();

        $pickedProductIds = $reseller->resellerCatalog()->pluck('products.id')->all();

        return view('storefront.account.reseller', [
            'company' => $company,
            'setting' => $company->storefrontSetting,
            'customer' => $reseller,
            'products' => $products,
            'pickedProductIds' => $pickedProductIds,
            'search' => $search,
            'storeUrl' => $this->storeUrl($company, $reseller),
            'previewSlug' => $this->previewSlug($request),
        ]);
    }

    public function updateSlug(Request $request): RedirectResponse
    {
        $company = $this->domainStorefront($request);
        $reseller = $this->approvedReseller($request);

        if ($reseller instanceof RedirectResponse) {
            return $reseller;
        }

        $data = $request->validate([
            'reseller_slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('customers', 'reseller_slug')
                    ->where('company_id', $company->getKey())
                    ->ignore($reseller->getKey()),
            ],
        ]);

        $reseller->update(['reseller_slug' => $data['reseller_slug']]);

        return redirect()
            ->to($this->editUrl($request))
            ->with('storefront_status', 'Your store URL has been updated.');
    }

    public function toggleProduct(Request $request, Product $product): RedirectResponse
    {
        $company = $this->domainStorefront($request);
        $reseller = $this->approvedReseller($request);

        if ($reseller instanceof RedirectResponse) {
            return $reseller;
        }

        // A reseller can only ever pick products from their own company's
        // catalog -- never another company's, even if a product id is
        // guessed/tampered with.
        abort_unless((int) $product->company_id === (int) $company->getKey(), 404);

        $existing = ResellerProduct::query()
            ->where('customer_id', $reseller->getKey())
            ->where('product_id', $product->getKey())
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            ResellerProduct::query()->create([
                'customer_id' => $reseller->getKey(),
                'product_id' => $product->getKey(),
                'is_active' => true,
            ]);
        }

        return redirect()
            ->to($this->editUrl($request))
            ->with('storefront_status', $existing ? "Removed \"{$product->name}\" from your store." : "Added \"{$product->name}\" to your store.");
    }

    protected function approvedReseller(Request $request): Customer|RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        if (! $customer instanceof Customer) {
            $route = $this->previewSlug($request)
                ? route('storefront.preview.account.login', $this->previewSlug($request))
                : route('storefront.account.login');

            return redirect()->to($route)->with('storefront_status', 'Log in to manage your store.');
        }

        abort_unless($customer->isApprovedReseller(), 403);

        return $customer;
    }

    protected function storeUrl(Company $company, Customer $reseller): ?string
    {
        if (blank($reseller->reseller_slug) || blank($company->domain)) {
            return null;
        }

        return 'https://'.$company->domain.route('storefront.reseller_store.products.index', $reseller->reseller_slug, false);
    }

    protected function editUrl(Request $request): string
    {
        return $this->previewSlug($request)
            ? route('storefront.preview.account.reseller', $this->previewSlug($request))
            : route('storefront.account.reseller');
    }

    protected function preparePreview(Request $request, Company $company): void
    {
        abort_unless($company->storefrontSetting?->is_published, 404);

        $request->attributes->set('storefront_company', $company);
        $request->attributes->set('storefront_preview_slug', $company->slug);
    }

    protected function previewSlug(Request $request): ?string
    {
        return $request->attributes->get('storefront_preview_slug');
    }

    protected function domainStorefront(Request $request): Company
    {
        $company = $request->attributes->get('storefront_company');

        abort_unless($company instanceof Company && $company->storefrontSetting?->is_published, 404);
        abort_unless($company->reseller_module_enabled, 404);

        $this->context->set($company);

        return $company;
    }
}
