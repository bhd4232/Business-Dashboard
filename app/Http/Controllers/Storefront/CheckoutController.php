<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StorefrontPayment;
use App\Models\StorefrontSetting;
use App\Services\CompanyContext;
use App\Services\StorefrontCart;
use App\Services\ZiniPayClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        protected CompanyContext $context,
        protected StorefrontCart $cart,
        protected ZiniPayClient $zinipay,
    ) {}

    /**
     * Advance amount payable online for pre-order lines: quantity beyond
     * current stock means the line is fulfilled as a pre-order, and its
     * full subtotal times the product's advance percent is due up front.
     */
    public static function advanceDue($items): float
    {
        return (float) collect($items)
            ->filter(fn (array $item): bool => ! ($item['variant'] ?? null)
                && $item['product']->is_preorder
                && $item['quantity'] > (int) $item['product']->stock)
            ->sum(fn (array $item): float => $item['subtotal'] * $item['product']->preorderAdvancePercent() / 100);
    }

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
        [$company, $setting] = $this->domainStorefront($request);
        $advanceDue = self::advanceDue($this->cart->items($company));
        $this->assertPayableCheckout($setting, $advanceDue);
        $order = $this->createOrder($request, $company);

        if ($advanceDue > 0) {
            return $this->startAdvancePayment($company, $setting, $order, $advanceDue,
                redirectUrl: route('storefront.checkout.success', $order),
                cancelUrl: route('storefront.checkout.success', $order),
            ) ?? redirect()->route('storefront.checkout.success', $order);
        }

        return redirect()->route('storefront.checkout.success', $order);
    }

    public function storePreview(Request $request, Company $company): RedirectResponse
    {
        $setting = $this->previewStorefront($company);
        $advanceDue = self::advanceDue($this->cart->items($company));
        $this->assertPayableCheckout($setting, $advanceDue);
        $order = $this->createOrder($request, $company);

        if ($advanceDue > 0) {
            return $this->startAdvancePayment($company, $setting, $order, $advanceDue,
                redirectUrl: route('storefront.preview.checkout.success', [$company->slug, $order]),
                cancelUrl: route('storefront.preview.checkout.success', [$company->slug, $order]),
            ) ?? redirect()->route('storefront.preview.checkout.success', [$company->slug, $order]);
        }

        return redirect()->route('storefront.preview.checkout.success', [$company->slug, $order]);
    }

    protected function assertPayableCheckout(StorefrontSetting $setting, float $advanceDue): void
    {
        if ($advanceDue > 0 && ! ZiniPayClient::isConfigured($setting)) {
            throw ValidationException::withMessages([
                'payment' => 'Pre-order items require an online advance payment, which is not available right now. Please contact the store.',
            ]);
        }
    }

    protected function startAdvancePayment(
        Company $company,
        StorefrontSetting $setting,
        Order $order,
        float $advanceDue,
        string $redirectUrl,
        string $cancelUrl,
    ): ?RedirectResponse {
        $payment = StorefrontPayment::query()->create([
            'company_id' => $company->getKey(),
            'order_id' => $order->getKey(),
            'gateway' => 'zinipay',
            'amount' => round($advanceDue, 2),
            'status' => StorefrontPayment::STATUS_PENDING,
        ]);

        try {
            $created = $this->zinipay->createPayment(
                $setting,
                $advanceDue,
                $order->customer_name,
                $order->customer?->email,
                $redirectUrl,
                $cancelUrl,
                webhookUrl: route('zinipay.webhook', $payment),
                metadata: ['order_number' => $order->order_number, 'payment_id' => $payment->getKey()],
            );
        } catch (\RuntimeException $exception) {
            $payment->update(['status' => StorefrontPayment::STATUS_FAILED, 'payload' => ['error' => $exception->getMessage()]]);
            Log::warning('ZiniPay payment creation failed', ['order' => $order->order_number, 'error' => $exception->getMessage()]);

            // The order is already placed; the store follows up for the advance manually.
            return null;
        }

        $payment->update(['invoice_id' => $created['invoice_id']]);

        return redirect()->away($created['payment_url']);
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
            'advanceDue' => self::advanceDue($items),
            'onlinePaymentAvailable' => ZiniPayClient::isConfigured($setting),
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

        // Keep the contact on the persisted cart so abandoned-cart
        // reminders can reach the customer if this checkout fails.
        $this->cart->rememberContact($company, $data['phone'], $data['name']);

        return DB::transaction(function () use ($company, $data, $items): Order {
            abort_if($items->isEmpty(), 422, 'Your cart is empty.');

            foreach ($items as $item) {
                $variant = $item['variant'] ?? null;
                $availableStock = $variant
                    ? (int) $variant->stock
                    : (int) $item['product']->stock;

                // Pre-order product lines may exceed current stock by design.
                if (! $variant && $item['product']->is_preorder) {
                    continue;
                }

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
