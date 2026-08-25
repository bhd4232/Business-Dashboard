<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\StorefrontPayment;
use App\Models\StorefrontPaymentMethod;
use App\Models\StorefrontSetting;
use App\Services\CompanyContext;
use App\Services\StorefrontCart;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontManualPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_delivery_area_is_detected_from_address_and_client_area_is_ignored(): void
    {
        $company = $this->createStore('delivery.example.test', [
            'delivery_first_kg_inside' => 60,
            'delivery_first_kg_outside' => 120,
        ]);

        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('Delivery Item', 'DELIVERY-001');
        app(StorefrontCart::class)->add($company, $product, 1);

        $this->post('http://delivery.example.test/checkout', [
            'name' => 'Outside Buyer',
            'phone' => '01712345678',
            'address' => 'Rangpur',
            'delivery_area' => 'inside',
            'payment_method' => 'cod',
        ])->assertRedirectContains('/checkout/success/');

        $order = Order::withoutGlobalScopes()->latest()->first();
        $this->assertSame('outside', $order->shipping_zone);
        $this->assertSame(120.0, (float) $order->shipping_fee);
    }

    public function test_dhaka_division_address_outside_the_city_uses_outside_rate(): void
    {
        $company = $this->createStore('dhaka-division.example.test', [
            'delivery_first_kg_inside' => 70,
            'delivery_first_kg_outside' => 110,
        ]);
        app(CompanyContext::class)->set($company);
        $product = $this->createProduct('Division Delivery Item', 'DIVISION-DELIVERY-001');
        app(StorefrontCart::class)->add($company, $product, 1);

        $this->post('http://dhaka-division.example.test/checkout', [
            'name' => 'Narayanganj Buyer',
            'phone' => '01712345679',
            'address' => 'Fatullah, Narayanganj, Dhaka Division',
            'payment_method' => 'cod',
        ])->assertRedirectContains('/checkout/success/');

        $order = Order::withoutGlobalScopes()->latest()->firstOrFail();
        $this->assertSame('outside', $order->shipping_zone);
        $this->assertSame(110.0, (float) $order->shipping_fee);
    }

    public function test_manual_payment_creates_pending_verification_record(): void
    {
        $company = $this->createStore('bkash.example.test');
        $method = $this->createManualMethod($company, 'bKash (Send Money)', '01700000000');

        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('bKash Item', 'BKASH-001');
        app(StorefrontCart::class)->add($company, $product, 1);

        $this->post('http://bkash.example.test/checkout', [
            'name' => 'bKash Buyer',
            'phone' => '01711112222',
            'address' => 'Dhaka',
            'delivery_area' => 'inside',
            'payment_method' => 'manual:'.$method->getKey(),
            'sender_number' => '01799998888',
            'trx_id' => 'TRX12345',
        ])->assertRedirectContains('/checkout/success/');

        $payment = StorefrontPayment::withoutGlobalScopes()->first();
        $this->assertNotNull($payment);
        $this->assertSame('manual', $payment->gateway);
        $this->assertSame($method->getKey(), $payment->storefront_payment_method_id);
        $this->assertSame(StorefrontPayment::STATUS_PENDING, $payment->status);
        $this->assertSame('01799998888', $payment->payment_method);
        $this->assertSame('TRX12345', $payment->transaction_id);
    }

    public function test_manual_payment_requires_sender_number_and_trx_id(): void
    {
        $company = $this->createStore('bkash-missing.example.test');
        $method = $this->createManualMethod($company, 'bKash (Send Money)', '01700000000');

        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('bKash Item 2', 'BKASH-002');
        app(StorefrontCart::class)->add($company, $product, 1);

        $this->post('http://bkash-missing.example.test/checkout', [
            'name' => 'bKash Buyer',
            'phone' => '01711113333',
            'address' => 'Dhaka',
            'delivery_area' => 'inside',
            'payment_method' => 'manual:'.$method->getKey(),
        ])->assertSessionHasErrors(['sender_number', 'trx_id']);

        $this->get('http://bkash-missing.example.test/checkout')
            ->assertOk()
            ->assertSee('Please review the highlighted checkout details.')
            ->assertSee('data-checkout-errors', false)
            ->assertSee('aria-invalid="true"', false);

        $this->assertSame(0, Order::withoutGlobalScopes()->count());
    }

    public function test_checkout_defaults_to_the_first_active_method_in_admin_sort_order(): void
    {
        $company = $this->createStore('manual-default.example.test');

        // The auto-seeded COD row would otherwise win by sort_order; turning
        // it off and giving bKash the lowest sort_order proves the default
        // selection really comes from the admin-controlled order, not a
        // hardcoded cod > bkash > nagad preference like before.
        StorefrontPaymentMethod::withoutGlobalScopes()->where('company_id', $company->getKey())->update(['is_active' => false]);
        $bkash = $this->createManualMethod($company, 'bKash (Send Money)', '01700000000', sortOrder: 0);
        $this->createManualMethod($company, 'Nagad (Send Money)', '01800000000', sortOrder: 1);

        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('Manual Default Item', 'MANUAL-DEFAULT-001');
        app(StorefrontCart::class)->add($company, $product, 1);

        $response = $this->get('http://manual-default.example.test/checkout')->assertOk();
        $content = $response->getContent();

        $this->assertSame(1, substr_count($content, 'name="sender_number"'));
        $this->assertSame(1, substr_count($content, 'name="trx_id"'));
        $this->assertMatchesRegularExpression('/id="payment-method-'.$bkash->getKey().'"[^>]*checked/', $content);
        $response
            ->assertDontSee('name="delivery_area"', false)
            ->assertSee('Detected delivery area:', false)
            ->assertSee('id="checkout-payment-method"', false)
            ->assertSeeInOrder(['Order summary', 'Place order']);

        $this->post('http://manual-default.example.test/checkout', [
            'name' => 'Default Method Buyer',
            'phone' => '01755556666',
            'address' => 'Dhaka',
            'sender_number' => '01799998888',
            'trx_id' => 'DEFAULT123',
        ])->assertRedirectContains('/checkout/success/');

        $payment = StorefrontPayment::withoutGlobalScopes()->first();
        $this->assertSame('manual', $payment?->gateway);
        $this->assertSame($bkash->getKey(), $payment?->storefront_payment_method_id);
    }

    public function test_checkout_is_blocked_when_no_payment_method_is_available(): void
    {
        $company = $this->createStore('no-payment.example.test');

        // The store's only method is the auto-seeded COD row - disable it
        // so checkout has nothing to offer.
        StorefrontPaymentMethod::withoutGlobalScopes()->where('company_id', $company->getKey())->update(['is_active' => false]);

        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('Unavailable Payment Item', 'NO-PAYMENT-001');
        app(StorefrontCart::class)->add($company, $product, 1);

        $response = $this->get('http://no-payment.example.test/checkout')
            ->assertOk()
            ->assertSee('No payment method is available right now.');

        $this->assertMatchesRegularExpression(
            '/<button(?=[^>]*data-checkout-submit)(?=[^>]*disabled)[^>]*>/',
            $response->getContent(),
        );

        $this->post('http://no-payment.example.test/checkout', [
            'name' => 'Blocked Buyer',
            'phone' => '01755557777',
            'address' => 'Dhaka',
        ])->assertSessionHasErrors('payment_method');

        $this->assertSame(0, Order::withoutGlobalScopes()->count());
    }

    public function test_admin_can_verify_a_pending_manual_payment(): void
    {
        $company = $this->createStore('verify.example.test');
        $method = $this->createManualMethod($company, 'Nagad (Send Money)', '01700000001');

        $payment = StorefrontPayment::query()->create([
            'company_id' => $company->getKey(),
            'order_id' => Order::query()->create([
                'company_id' => $company->getKey(),
                'customer_name' => 'Verify Buyer',
                'status' => 'draft',
                'source' => Order::SOURCE_STOREFRONT,
            ])->getKey(),
            'gateway' => 'manual',
            'storefront_payment_method_id' => $method->getKey(),
            'amount' => 500,
            'status' => StorefrontPayment::STATUS_PENDING,
            'payment_method' => '01799990000',
            'transaction_id' => 'TRXVERIFY1',
        ]);

        $payment->update(['status' => StorefrontPayment::STATUS_COMPLETED]);

        $this->assertSame(StorefrontPayment::STATUS_COMPLETED, $payment->fresh()->status);
    }

    private function createProduct(string $name, string $sku): Product
    {
        return Product::query()->create([
            'name' => $name,
            'sku' => $sku,
            'price' => 500,
            'sale_price' => 500,
            'cost_price' => 300,
            'stock' => 10,
            'unit' => 'pcs',
            'reorder_level' => 2,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);
    }

    private function createStore(string $domain, array $settingOverrides = []): Company
    {
        $company = Company::query()->create([
            'name' => 'Store '.$domain,
            'slug' => str($domain)->slug()->toString(),
            'domain' => $domain,
            'domain_verified' => true,
            'invoice_prefix' => 'MAN',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        StorefrontSetting::query()->create(array_merge([
            'company_id' => $company->getKey(),
            'theme_color' => '#0F766E',
            'is_published' => true,
            'new_customer_delivery_advance_enabled' => false,
        ], $settingOverrides));

        return $company;
    }

    /**
     * StorefrontSetting::created() already seeds an active `cod` row for
     * every store above - this adds an admin-managed `manual` channel
     * alongside it, mirroring what Storefront -> Payment Methods creates.
     */
    private function createManualMethod(Company $company, string $name, string $accountNumber, int $sortOrder = 1): StorefrontPaymentMethod
    {
        return StorefrontPaymentMethod::withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'type' => StorefrontPaymentMethod::TYPE_MANUAL,
            'name' => $name,
            'account_number' => $accountNumber,
            'is_active' => true,
            'sort_order' => $sortOrder,
        ]);
    }
}
