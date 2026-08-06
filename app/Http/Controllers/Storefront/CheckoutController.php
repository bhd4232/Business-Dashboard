<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Jobs\CheckExternalCourierFraudJob;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StorefrontCustomerActivity;
use App\Models\StorefrontPayment;
use App\Models\StorefrontSetting;
use App\Services\CompanyContext;
use App\Services\StorefrontCart;
use App\Services\StorefrontCustomerActivityService;
use App\Services\StorefrontDeliveryAreaResolver;
use App\Services\StorefrontDeliveryService;
use App\Services\StorefrontPaymentService;
use App\Services\ZiniPayClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        protected CompanyContext $context,
        protected StorefrontCart $cart,
        protected ZiniPayClient $zinipay,
        protected StorefrontCustomerActivityService $activities,
        protected StorefrontDeliveryService $delivery,
        protected StorefrontDeliveryAreaResolver $deliveryAreas,
        protected StorefrontPaymentService $payments,
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
        $items = $this->cart->items($company);
        $data = $this->validatedCheckout($request, $setting);
        $data['delivery_area'] = $this->deliveryAreas->resolve($data['address'], $setting);
        $quote = $this->delivery->quote($items, $data['delivery_area'], $setting);
        $isNewCustomer = ! Customer::query()->where('phone', $data['phone'])->exists();
        $advanceDue = self::advanceDue($items);

        if ($isNewCustomer && $advanceDue <= 0 && ($setting->new_customer_delivery_advance_enabled ?? true)) {
            $this->assertOnlinePaymentAvailable($setting, 'New customers must pay the delivery charge online before the order can be placed.');

            return $this->startNewCustomerPayment($company, $setting, $data, $items, $quote);
        }

        $this->assertPayableCheckout($setting, $advanceDue);
        $order = $this->createOrder($company, $data, $items, $quote['fee']);
        $successUrl = $this->signedSuccessUrl($order);
        $customer = auth('customer')->user();

        if ($customer instanceof Customer && $customer->getKey() === $order->customer_id) {
            $this->activities->record(
                $customer,
                StorefrontCustomerActivity::TYPE_ORDER_PLACED,
                'Order placed',
                "Order {$order->order_number} was submitted successfully.",
                ['order_number' => $order->order_number, 'total_amount' => (float) $order->total_amount],
            );
        }

        if ($advanceDue > 0) {
            return $this->startAdvancePayment($company, $setting, $order, $advanceDue,
                redirectUrl: $successUrl,
                cancelUrl: $successUrl,
            ) ?? redirect()->to($successUrl);
        }

        return redirect()->to($successUrl);
    }

    public function storePreview(Request $request, Company $company): RedirectResponse
    {
        $setting = $this->previewStorefront($company);
        $items = $this->cart->items($company);
        $data = $this->validatedCheckout($request, $setting);
        $data['delivery_area'] = $this->deliveryAreas->resolve($data['address'], $setting);
        $quote = $this->delivery->quote($items, $data['delivery_area'], $setting);
        $isNewCustomer = ! Customer::query()->where('phone', $data['phone'])->exists();
        $advanceDue = self::advanceDue($items);

        if ($isNewCustomer && $advanceDue <= 0 && ($setting->new_customer_delivery_advance_enabled ?? true)) {
            $this->assertOnlinePaymentAvailable($setting, 'New customers must pay the delivery charge online before the order can be placed.');

            return $this->startNewCustomerPayment($company, $setting, $data, $items, $quote, $company->slug);
        }

        $this->assertPayableCheckout($setting, $advanceDue);
        $order = $this->createOrder($company, $data, $items, $quote['fee']);

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

    protected function assertOnlinePaymentAvailable(StorefrontSetting $setting, string $message): void
    {
        if (! ZiniPayClient::isConfigured($setting)) {
            throw ValidationException::withMessages(['payment' => $message.' No active online payment gateway is available right now.']);
        }
    }

    /**
     * Start a hosted delivery-charge payment without creating a Customer or
     * Order. The verified webhook/return flow places both records atomically.
     */
    protected function startNewCustomerPayment(
        Company $company,
        StorefrontSetting $setting,
        array $data,
        $items,
        array $quote,
        ?string $previewSlug = null,
    ): RedirectResponse {
        $this->cart->rememberContact($company, $data['phone'], $data['name']);
        $this->assertCartStock($items);

        $payment = StorefrontPayment::query()->create([
            'company_id' => $company->getKey(),
            'order_id' => null,
            'gateway' => 'zinipay',
            'purpose' => StorefrontPayment::PURPOSE_NEW_CUSTOMER_DELIVERY,
            'amount' => round((float) $quote['fee'], 2),
            'status' => StorefrontPayment::STATUS_PENDING,
            'checkout_data' => [
                ...$data,
                'shipping_fee' => (float) $quote['fee'],
                'total_weight' => (float) $quote['weight'],
                'billed_weight' => (int) $quote['billed_weight'],
                'items' => collect($items)->map(fn (array $item): array => [
                    'product_id' => $item['product']->getKey(),
                    'product_variant_id' => ($item['variant'] ?? null)?->getKey(),
                    'variant_label' => ($item['variant'] ?? null)?->label(),
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => (float) $item['unit_price'],
                    'unit_cost' => (float) (($item['variant'] ?? null)?->cost_price ?? $item['product']->cost_price ?? 0),
                ])->values()->all(),
            ],
        ]);

        $redirectUrl = $previewSlug
            ? URL::temporarySignedRoute('storefront.preview.checkout.payment-return', now()->addHour(), ['company' => $previewSlug, 'payment' => $payment])
            : URL::temporarySignedRoute('storefront.checkout.payment-return', now()->addHour(), ['payment' => $payment]);
        $cancelUrl = $previewSlug
            ? route('storefront.preview.checkout.show', $previewSlug)
            : route('storefront.checkout.show');

        try {
            $created = $this->zinipay->createPayment(
                $setting,
                (float) $quote['fee'],
                $data['name'],
                $data['email'],
                $redirectUrl,
                $cancelUrl,
                webhookUrl: route('zinipay.webhook', $payment),
                metadata: ['payment_id' => $payment->getKey(), 'purpose' => StorefrontPayment::PURPOSE_NEW_CUSTOMER_DELIVERY],
            );
        } catch (\Throwable $exception) {
            $payment->update(['status' => StorefrontPayment::STATUS_FAILED, 'payload' => ['error' => $exception->getMessage()]]);
            Log::warning('New-customer delivery payment creation failed', ['payment' => $payment->getKey(), 'error' => $exception->getMessage()]);

            throw ValidationException::withMessages(['payment' => 'The secure payment page could not be opened. No order was placed; please try again.']);
        }

        $payment->update(['invoice_id' => $created['invoice_id']]);

        return redirect()->away($created['payment_url']);
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
            'purpose' => StorefrontPayment::PURPOSE_PREORDER_ADVANCE,
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
        } catch (\Throwable $exception) {
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

        $customer = auth('customer')->user();
        $customerOwnsOrder = $customer
            && $customer->company_id === $company->getKey()
            && $customer->getKey() === $order->customer_id;

        abort_unless($request->hasValidSignature() || $customerOwnsOrder, 404);

        return view('storefront.checkout.success', [
            'company' => $company,
            'setting' => $setting,
            'order' => $order->load('items.product', 'customer'),
        ]);
    }

    protected function signedSuccessUrl(Order $order): string
    {
        return URL::temporarySignedRoute(
            'storefront.checkout.success',
            now()->addDay(),
            ['order' => $order->getRouteKey()],
        );
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

    public function paymentReturn(Request $request, StorefrontPayment $payment): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);
        [$company, $setting] = $this->domainStorefront($request);

        return $this->completePaymentReturn($payment, $company, $setting);
    }

    public function paymentReturnPreview(Request $request, Company $company, StorefrontPayment $payment): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);
        $setting = $this->previewStorefront($company);

        return $this->completePaymentReturn($payment, $company, $setting, $company->slug);
    }

    protected function completePaymentReturn(
        StorefrontPayment $payment,
        Company $company,
        StorefrontSetting $setting,
        ?string $previewSlug = null,
    ): RedirectResponse {
        abort_unless($payment->company_id === $company->getKey(), 404);

        try {
            $order = $this->payments->verifyAndFinalize($payment, $setting);
        } catch (\Throwable $exception) {
            Log::warning('Storefront payment return could not be finalized', [
                'payment' => $payment->getKey(),
                'error' => $exception->getMessage(),
            ]);

            $order = null;
        }

        if (! $order) {
            return redirect($previewSlug
                ? route('storefront.preview.checkout.show', $previewSlug)
                : route('storefront.checkout.show'))
                ->withErrors(['payment' => 'Payment was not completed or could not be verified. No order was placed.']);
        }

        $this->cart->clear($company);

        return $previewSlug
            ? redirect()->route('storefront.preview.checkout.success', [$previewSlug, $order])
            : redirect()->to($this->signedSuccessUrl($order));
    }

    protected function checkoutView(Company $company, StorefrontSetting $setting, ?string $previewSlug = null): View|RedirectResponse
    {
        $items = $this->cart->items($company);

        if ($items->isEmpty()) {
            return redirect($previewSlug
                ? route('storefront.preview.cart.show', $previewSlug)
                : route('storefront.cart.show'))->with('storefront_status', 'Add products before checkout.');
        }

        $insideQuote = $this->delivery->quote($items, 'inside', $setting);
        $outsideQuote = $this->delivery->quote($items, 'outside', $setting);

        return view('storefront.checkout.show', [
            'company' => $company,
            'setting' => $setting,
            'previewSlug' => $previewSlug,
            'items' => $items,
            'subtotal' => $this->cart->subtotal($company),
            'advanceDue' => self::advanceDue($items),
            'onlinePaymentAvailable' => ZiniPayClient::isConfigured($setting),
            'insideQuote' => $insideQuote,
            'outsideQuote' => $outsideQuote,
            'insideDhakaKeywords' => $this->deliveryAreas->keywords($setting),
            'outsideDhakaMarkers' => $this->deliveryAreas->outsideMarkers(),
        ]);
    }

    protected function validatedCheckout(Request $request, StorefrontSetting $setting): array
    {
        $defaultPaymentMethod = $this->defaultPaymentMethod($setting);

        if (! $request->filled('payment_method') && $defaultPaymentMethod !== null) {
            $request->merge(['payment_method' => $defaultPaymentMethod]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', 'in:cod,manual_bkash,manual_nagad'],
            'sender_number' => ['required_if:payment_method,manual_bkash,manual_nagad', 'nullable', 'string', 'max:20'],
            'trx_id' => ['required_if:payment_method,manual_bkash,manual_nagad', 'nullable', 'string', 'max:40'],
        ]);

        if ($data['payment_method'] === 'cod' && ! ($setting->cod_enabled ?? true)) {
            throw ValidationException::withMessages(['payment_method' => 'Cash on Delivery is not available right now. Please choose another payment method.']);
        }

        if ($data['payment_method'] === 'manual_bkash' && blank($setting->manual_bkash_number)) {
            throw ValidationException::withMessages(['payment_method' => 'bKash payment is not available right now.']);
        }

        if ($data['payment_method'] === 'manual_nagad' && blank($setting->manual_nagad_number)) {
            throw ValidationException::withMessages(['payment_method' => 'Nagad payment is not available right now.']);
        }

        $data['phone'] = trim($data['phone']);
        $data['email'] = $data['email'] ?? null;

        return $data;
    }

    protected function createOrder(Company $company, array $data, $items, float $deliveryCharge): Order
    {
        // Keep the contact on the persisted cart so abandoned-cart
        // reminders can reach the customer if this checkout fails.
        $this->cart->rememberContact($company, $data['phone'], $data['name']);

        return DB::transaction(function () use ($company, $data, $items, $deliveryCharge): Order {
            abort_if($items->isEmpty(), 422, 'Your cart is empty.');

            $this->assertCartStock($items);

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
                'shipping_zone' => $data['delivery_area'],
                'shipping_fee' => $deliveryCharge,
                'paid_amount' => 0,
                'status' => 'draft',
                'source' => Order::SOURCE_STOREFRONT,
                'note' => trim("Storefront checkout\nDelivery address: {$data['address']}\nPayment method: ".self::paymentMethodLabel($data['payment_method'])."\n".(($data['note'] ?? null) ? "Customer note: {$data['note']}" : '')),
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

            if (in_array($data['payment_method'], ['manual_bkash', 'manual_nagad'], true)) {
                StorefrontPayment::query()->create([
                    'company_id' => $company->getKey(),
                    'order_id' => $order->getKey(),
                    'gateway' => $data['payment_method'],
                    'amount' => round((float) $order->total_amount, 2),
                    'status' => StorefrontPayment::STATUS_PENDING,
                    'payment_method' => $data['sender_number'],
                    'transaction_id' => $data['trx_id'],
                ]);
            }

            $this->cart->clear($company);

            // Customer never sees this — the external check happens in the
            // background and only surfaces to staff as a review requirement
            // if the cross-courier success ratio is low (Part 3.8).
            CheckExternalCourierFraudJob::dispatch($order->getKey())->afterCommit();

            return $order;
        });
    }

    protected function assertCartStock($items): void
    {
        abort_if(collect($items)->isEmpty(), 422, 'Your cart is empty.');

        foreach ($items as $item) {
            $variant = $item['variant'] ?? null;
            $availableStock = $variant ? (int) $variant->stock : (int) $item['product']->stock;

            if (! $variant && $item['product']->is_preorder) {
                continue;
            }

            abort_if($item['quantity'] > $availableStock, 422, "{$item['product']->name} does not have enough stock.");
        }
    }

    protected function defaultPaymentMethod(StorefrontSetting $setting): ?string
    {
        if ($setting->cod_enabled ?? true) {
            return 'cod';
        }

        if (filled($setting->manual_bkash_number)) {
            return 'manual_bkash';
        }

        if (filled($setting->manual_nagad_number)) {
            return 'manual_nagad';
        }

        return null;
    }

    protected static function paymentMethodLabel(string $method): string
    {
        return match ($method) {
            'manual_bkash' => 'bKash (Send Money, pending verification)',
            'manual_nagad' => 'Nagad (Send Money, pending verification)',
            default => 'Cash on Delivery',
        };
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
