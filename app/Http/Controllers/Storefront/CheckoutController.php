<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StorefrontSetting;
use App\Services\CompanyContext;
use App\Services\StorefrontCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        protected CompanyContext $context,
        protected StorefrontCart $cart,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        [$company, $setting] = $this->domainStorefront($request);

        return $this->checkoutView($company, $setting);
    }

    public function showPreview(Company $company): View|RedirectResponse
    {
        $setting = $this->previewStorefront($company);

        return $this->checkoutView($company, $setting, $company->slug);
    }

    public function store(Request $request): RedirectResponse
    {
        [$company] = $this->domainStorefront($request);
        $order = $this->createOrder($request, $company);

        return redirect()->route('storefront.checkout.success', $order);
    }

    public function storePreview(Request $request, Company $company): RedirectResponse
    {
        $this->previewStorefront($company);
        $order = $this->createOrder($request, $company);

        return redirect()->route('storefront.preview.checkout.success', [$company->slug, $order]);
    }

    public function success(Request $request, Order $order): View
    {
        [$company, $setting] = $this->domainStorefront($request);

        abort_unless($order->company_id === $company->getKey() && $order->source === Order::SOURCE_STOREFRONT, 404);

        return view('storefront.checkout.success', [
            'company' => $company,
            'setting' => $setting,
            'order' => $order->load('items.product', 'customer'),
        ]);
    }

    public function successPreview(Company $company, Order $order): View
    {
        $setting = $this->previewStorefront($company);

        abort_unless($order->company_id === $company->getKey() && $order->source === Order::SOURCE_STOREFRONT, 404);

        return view('storefront.checkout.success', [
            'company' => $company,
            'setting' => $setting,
            'previewSlug' => $company->slug,
            'order' => $order->load('items.product', 'customer'),
        ]);
    }

    protected function checkoutView(Company $company, StorefrontSetting $setting, ?string $previewSlug = null): View|RedirectResponse
    {
        $items = $this->cart->items($company);

        if ($items->isEmpty()) {
            return redirect($previewSlug
                ? route('storefront.preview.cart.show', $previewSlug)
                : route('storefront.cart.show'))->with('storefront_status', 'Add products before checkout.');
        }

        return view('storefront.checkout.show', [
            'company' => $company,
            'setting' => $setting,
            'previewSlug' => $previewSlug,
            'items' => $items,
            'subtotal' => $this->cart->subtotal($company),
        ]);
    }

    protected function createOrder(Request $request, Company $company): Order
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $items = $this->cart->items($company);

        return DB::transaction(function () use ($company, $data, $items): Order {
            abort_if($items->isEmpty(), 422, 'Your cart is empty.');

            foreach ($items as $item) {
                $availableStock = $item['variant'] ?? null
                    ? (int) $item['variant']->stock
                    : (int) $item['product']->stock;

                abort_if($item['quantity'] > $availableStock, 422, "{$item['product']->name} does not have enough stock.");
            }

            $customer = Customer::query()->firstOrNew([
                'phone' => $data['phone'],
            ]);

            $customer->fill([
                'name' => $data['name'],
                'email' => $data['email'] ?? $customer->email,
                'address' => $data['address'],
                'customer_type' => $customer->customer_type ?: 'regular',
                'customer_source' => 'website',
                'opening_balance' => $customer->opening_balance ?? 0,
                'is_active' => true,
            ]);
            $customer->save();

            $order = Order::query()->create([
                'customer_id' => $customer->getKey(),
                'customer_name' => $customer->name,
                'order_date' => now()->toDateString(),
                'discount' => 0,
                'vat' => 0,
                'paid_amount' => 0,
                'status' => 'draft',
                'source' => Order::SOURCE_STOREFRONT,
                'note' => trim("Storefront checkout\nDelivery address: {$data['address']}\n".(($data['note'] ?? null) ? "Customer note: {$data['note']}" : '')),
            ]);

            foreach ($items as $item) {
                $variant = $item['variant'] ?? null;

                OrderItem::query()->create([
                    'order_id' => $order->getKey(),
                    'product_id' => $item['product']->getKey(),
                    'product_variant_id' => $variant?->getKey(),
                    'variant_label' => $variant?->label(),
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'unit_cost' => ($variant?->cost_price ?? $item['product']->cost_price) ?? 0,
                ]);
            }

            $order->refresh();
            $this->cart->clear($company);

            return $order;
        });
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
