<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\MatchesCustomerPhone;
use App\Models\Company;
use App\Models\Order;
use App\Models\StorefrontSetting;
use App\Services\CompanyContext;
use App\Services\StorefrontCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AccountOrdersController extends Controller
{
    use MatchesCustomerPhone;

    public function __construct(protected CompanyContext $context, protected StorefrontCart $cart) {}

    public function index(Request $request): View
    {
        [$company, $setting] = $this->domainStorefront($request);

        return $this->ordersView($request, $company, $setting);
    }

    public function indexPreview(Request $request, Company $company): View
    {
        $setting = $this->previewStorefront($company);

        return $this->ordersView($request, $company, $setting, $company->slug);
    }

    public function reorder(Request $request, string $orderNo): RedirectResponse
    {
        [$company] = $this->domainStorefront($request);

        $this->reorderIntoCart($request, $company, $orderNo);

        return redirect()->route('storefront.cart.show');
    }

    public function reorderPreview(Request $request, Company $company, string $orderNo): RedirectResponse
    {
        $this->previewStorefront($company);

        $this->reorderIntoCart($request, $company, $orderNo);

        return redirect()->route('storefront.preview.cart.show', $company->slug);
    }

    protected function reorderIntoCart(Request $request, Company $company, string $orderNo): void
    {
        $phone = trim((string) $request->string('phone'));

        abort_if($phone === '', 404);

        $order = $this->ordersForPhone($company, $phone)
            ->firstWhere('order_number', $orderNo);

        abort_unless($order, 404);

        $added = 0;

        foreach ($order->items as $item) {
            $product = $item->product;

            if (! $product || ! $product->is_active || $product->status !== \App\Models\Product::STATUS_AVAILABLE) {
                continue;
            }

            $variant = $item->product_variant_id
                ? $product->activeVariants()->whereKey($item->product_variant_id)->first()
                : null;

            if ($item->product_variant_id && ! $variant) {
                continue;
            }

            $this->cart->add($company, $product, (int) $item->quantity, $variant);
            $added++;
        }

        session()->flash('storefront_status', $added > 0
            ? "Added {$added} ".str('item')->plural($added)." from order {$order->order_number} to your cart."
            : 'None of the items from that order are currently available.');
    }

    protected function ordersView(
        Request $request,
        Company $company,
        StorefrontSetting $setting,
        ?string $previewSlug = null,
    ): View {
        $phone = trim((string) $request->string('phone'));
        $orders = $phone === '' ? collect() : $this->ordersForPhone($company, $phone);

        return view('storefront.account.orders', [
            'company' => $company,
            'setting' => $setting,
            'previewSlug' => $previewSlug,
            'phone' => $phone,
            'orders' => $orders,
            'hasSearched' => $request->filled('phone'),
        ]);
    }

    protected function ordersForPhone(Company $company, string $phone): Collection
    {
        return Order::query()
            ->with(['customer', 'items.product'])
            ->where('company_id', $company->getKey())
            ->where('source', Order::SOURCE_STOREFRONT)
            ->tap(fn ($query) => $this->whereCustomerPhoneMatches($query, $phone))
            ->latest('order_date')
            ->latest('id')
            ->get();
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
}
