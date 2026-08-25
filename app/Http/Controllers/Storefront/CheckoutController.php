<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Jobs\CheckExternalCourierFraudJob;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StorefrontCheckoutAttempt;
use App\Models\StorefrontCustomerActivity;
use App\Models\StorefrontPayment;
use App\Models\StorefrontSetting;
use App\Services\CompanyContext;
use App\Services\PaymentGatewayResolver;
use App\Services\StorefrontCart;
use App\Services\StorefrontCheckoutPolicyService;
use App\Services\StorefrontCustomerActivityService;
use App\Services\StorefrontDeliveryAreaResolver;
use App\Services\StorefrontDeliveryService;
use App\Services\StorefrontMetaDispatchService;
use App\Services\StorefrontMetaTrackingService;
use App\Services\StorefrontPaymentEligibilityService;
use App\Services\StorefrontPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        protected CompanyContext $context,
        protected StorefrontCart $cart,
        protected PaymentGatewayResolver $gateways,
        protected StorefrontCustomerActivityService $activities,
        protected StorefrontCheckoutPolicyService $checkoutPolicies,
        protected StorefrontDeliveryService $delivery,
        protected StorefrontDeliveryAreaResolver $deliveryAreas,
        protected StorefrontPaymentService $payments,
        protected StorefrontPaymentEligibilityService $paymentEligibility,
        protected StorefrontMetaTrackingService $metaTracking,
        protected StorefrontMetaDispatchService $metaDispatch,
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
        $data['meta_tracking_context'] = $this->metaTracking->requestContext($request, $setting);
        $data['delivery_area'] = $this->deliveryAreas->resolve($data['address'], $setting);
        $quote = $this->delivery->quote($items, $data['delivery_area'], $setting);
        $this->cart->startCheckout(
            $company,
            $this->cart->subtotal($company) + (float) $quote['fee'],
            $data,
        );
        [$data, $checkoutAttempt] = $this->checkoutPolicies->evaluate(
            $request,
            $company,
            $setting,
            $data,
            $this->cart->subtotal($company) + (float) $quote['fee'],
        );
        $customer = Customer::query()->whereIn('phone', $this->checkoutPolicies->phoneVariants($data['phone']))->first();
        $decision = $this->paymentEligibility->decide(
            $company,
            $setting,
            $data['phone'],
            $this->cart->subtotal($company) + (float) $quote['fee'],
            self::advanceDue($items),
            ! $customer && ($setting->new_customer_delivery_advance_enabled ?? true) ? (float) $quote['fee'] : 0,
            $customer,
        );
        $data['courier_success_ratio'] = $decision['courier_success_ratio'];
        $this->checkoutPolicies->recordPaymentDecision($checkoutAttempt, $decision);

        if ($decision['required_advance'] > 0) {
            $this->assertOnlinePaymentAvailable($setting, 'This order requires a verified online advance before it can be placed.');

            return $this->startCheckoutAdvancePayment($company, $setting, $data, $items, $quote, $decision, checkoutAttempt: $checkoutAttempt);
        }

        $order = $this->createOrder($company, $data, $items, $quote['fee']);
        $this->checkoutPolicies->markAccepted($checkoutAttempt, $order);
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

        return redirect()->to($successUrl);
    }

    public function storePreview(Request $request, Company $company): RedirectResponse
    {
        $setting = $this->previewStorefront($company);
        $items = $this->cart->items($company);
        $data = $this->validatedCheckout($request, $setting);
        $data['delivery_area'] = $this->deliveryAreas->resolve($data['address'], $setting);
        $quote = $this->delivery->quote($items, $data['delivery_area'], $setting);
        $this->cart->startCheckout(
            $company,
            $this->cart->subtotal($company) + (float) $quote['fee'],
            $data,
        );
        [$data, $checkoutAttempt] = $this->checkoutPolicies->evaluate(
            $request,
            $company,
            $setting,
            $data,
            $this->cart->subtotal($company) + (float) $quote['fee'],
        );
        $customer = Customer::query()->whereIn('phone', $this->checkoutPolicies->phoneVariants($data['phone']))->first();
        $decision = $this->paymentEligibility->decide(
            $company,
            $setting,
            $data['phone'],
            $this->cart->subtotal($company) + (float) $quote['fee'],
            self::advanceDue($items),
            ! $customer && ($setting->new_customer_delivery_advance_enabled ?? true) ? (float) $quote['fee'] : 0,
            $customer,
        );
        $this->checkoutPolicies->recordPaymentDecision($checkoutAttempt, $decision);

        if ($decision['required_advance'] > 0) {
            $this->assertOnlinePaymentAvailable($setting, 'This order requires a verified online advance before it can be placed.');

            return $this->startCheckoutAdvancePayment($company, $setting, $data, $items, $quote, $decision, $company->slug, $checkoutAttempt);
        }

        $order = $this->createOrder($company, $data, $items, $quote['fee']);
        $this->checkoutPolicies->markAccepted($checkoutAttempt, $order);

        return redirect()->route('storefront.preview.checkout.success', [$company->slug, $order]);
    }

    public function autosave(Request $request): Response
    {
        [$company, $setting] = $this->domainStorefront($request);

        return $this->persistAutosave($request, $company, $setting);
    }

    public function autosavePreview(Request $request, Company $company): Response
    {
        $setting = $this->previewStorefront($company);

        return $this->persistAutosave($request, $company, $setting);
    }

    protected function persistAutosave(Request $request, Company $company, StorefrontSetting $setting): Response
    {
        if (! (bool) $setting->checkout_autosave_enabled) {
            return response()->noContent();
        }

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->cart->rememberCheckout($company, $data, $this->cart->subtotal($company));

        return response()->noContent();
    }

    protected function assertOnlinePaymentAvailable(StorefrontSetting $setting, string $message): void
    {
        if (! $this->gateways->isConfigured($setting)) {
            throw ValidationException::withMessages(['payment' => $message.' No active online payment gateway is available right now.']);
        }
    }

    /**
     * Start one hosted checkout-advance payment without creating a Customer
     * or Order. The verified webhook/return flow places both atomically.
     */
    protected function startCheckoutAdvancePayment(
        Company $company,
        StorefrontSetting $setting,
        array $data,
        $items,
        array $quote,
        array $decision,
        ?string $previewSlug = null,
        ?StorefrontCheckoutAttempt $checkoutAttempt = null,
    ): RedirectResponse {
        $this->cart->rememberContact($company, $data['phone'], $data['name']);
        $this->assertCartStock($items);

        $payment = StorefrontPayment::query()->create([
            'company_id' => $company->getKey(),
            'order_id' => null,
            'gateway' => $setting->online_payment_gateway ?: 'zinipay',
            'purpose' => StorefrontPayment::PURPOSE_CHECKOUT_ADVANCE,
            'amount' => $decision['required_advance'],
            'status' => StorefrontPayment::STATUS_PENDING,
            'checkout_data' => [
                ...$data,
                'shipping_fee' => (float) $quote['fee'],
                'total_weight' => (float) $quote['weight'],
                'billed_weight' => (int) $quote['billed_weight'],
                'checkout_attempt_id' => $checkoutAttempt?->getKey(),
                'cart_record_id' => $this->cart->recordId($company),
                'advance_reasons' => $decision['reasons'],
                'courier_lookup_status' => $decision['courier_lookup_status'],
                'courier_success_ratio' => $decision['courier_success_ratio'],
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

        $gateway = $setting->online_payment_gateway ?: 'zinipay';
        // Used as PayStation's own invoice_number since PayStation never
        // echoes one back (ZiniPay ignores this and derives its own invoice
        // id instead). Deliberately NOT just the StorefrontPayment's own
        // primary key: PayStation tracks invoice numbers on its own side
        // permanently, independent of this app's database, so a local table
        // reset (migrate:fresh, demo refresh, restore) that restarts the
        // auto-increment sequence would resend an invoice_number PayStation
        // already saw before and gets rejected with "Duplicate invoice
        // number" — confirmed live in production. The random suffix makes
        // this unique regardless of local ID reuse; the payment id prefix
        // keeps it traceable back to the StorefrontPayment row.
        $merchantReference = $payment->getKey().'-'.Str::random(10);

        try {
            $created = $this->gateways->forSetting($setting)->createPayment(
                $setting,
                $decision['required_advance'],
                $data['name'],
                $data['email'],
                $data['phone'],
                $data['address'],
                $redirectUrl,
                $cancelUrl,
                webhookUrl: route($gateway.'.webhook', $payment),
                merchantReference: $merchantReference,
                metadata: ['payment_id' => $payment->getKey(), 'purpose' => StorefrontPayment::PURPOSE_CHECKOUT_ADVANCE],
            );
        } catch (\Throwable $exception) {
            $payment->update(['status' => StorefrontPayment::STATUS_FAILED, 'payload' => ['error' => $exception->getMessage()]]);
            Log::warning('Checkout advance payment creation failed', ['payment' => $payment->getKey(), 'error' => $exception->getMessage()]);

            throw ValidationException::withMessages(['payment' => 'The secure payment page could not be opened. No order was placed; please try again.']);
        }

        $payment->update(['invoice_id' => $created['invoice_id']]);
        $this->checkoutPolicies->markPendingPayment($checkoutAttempt);

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
        // PayStation appends invoice_number/trx_id onto this signed URL
        // when it redirects the browser back — those weren't part of the
        // originally-signed query string, so a plain hasValidSignature()
        // would reject every real PayStation return. Ignoring just those
        // two param names is a no-op for ZiniPay (which never appends them).
        abort_unless($request->hasValidSignatureWhileIgnoring(['invoice_number', 'trx_id']), 403);
        [$company, $setting] = $this->domainStorefront($request);

        return $this->completePaymentReturn($payment, $company, $setting, transactionId: $request->query('trx_id'));
    }

    public function paymentReturnPreview(Request $request, Company $company, StorefrontPayment $payment): RedirectResponse
    {
        abort_unless($request->hasValidSignatureWhileIgnoring(['invoice_number', 'trx_id']), 403);
        $setting = $this->previewStorefront($company);

        return $this->completePaymentReturn($payment, $company, $setting, $company->slug, $request->query('trx_id'));
    }

    protected function completePaymentReturn(
        StorefrontPayment $payment,
        Company $company,
        StorefrontSetting $setting,
        ?string $previewSlug = null,
        ?string $transactionId = null,
    ): RedirectResponse {
        abort_unless($payment->company_id === $company->getKey(), 404);

        try {
            $order = $this->payments->verifyAndFinalize($payment, $setting, transactionId: $transactionId);
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

        $this->cart->clear($company, $order);

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
        $loggedInCustomer = auth('customer')->user();

        if ((bool) $setting->checkout_autosave_enabled) {
            $this->cart->startCheckout($company, $this->cart->subtotal($company), [
                'name' => $loggedInCustomer?->name,
                'phone' => $loggedInCustomer?->phone,
                'email' => $loggedInCustomer?->email,
                'address' => $loggedInCustomer?->address,
            ]);
        }

        return view('storefront.checkout.show', [
            'company' => $company,
            'setting' => $setting,
            'previewSlug' => $previewSlug,
            'items' => $items,
            'subtotal' => $this->cart->subtotal($company),
            'advanceDue' => self::advanceDue($items),
            'onlinePaymentAvailable' => $this->gateways->isConfigured($setting),
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
        $data['phone'] = $this->checkoutPolicies->normalizeLocalPhone($data['phone']) ?: $data['phone'];
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

            $customer = Customer::query()
                ->whereIn('phone', $this->checkoutPolicies->phoneVariants($data['phone']))
                ->first() ?? new Customer(['phone' => $data['phone']]);

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
            $cartRecordId = $this->cart->recordId($company);

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

            $this->cart->clear($company, $order);

            // Customer never sees this — the external check happens in the
            // background and only surfaces to staff as a review requirement
            // if the cross-courier success ratio is low (Part 3.8).
            CheckExternalCourierFraudJob::dispatch($order->getKey())->afterCommit();

            $this->metaDispatch->captureOrder(
                $order,
                $company->storefrontSetting,
                (array) ($data['meta_tracking_context'] ?? []),
                isset($data['courier_success_ratio']) ? (float) $data['courier_success_ratio'] : null,
                $cartRecordId,
            );

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
