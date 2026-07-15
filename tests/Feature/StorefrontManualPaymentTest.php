<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\StorefrontPayment;
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

    public function test_delivery_area_sets_shipping_fee_on_the_order(): void
    {
        $company = $this->createStore('delivery.example.test', [
            'delivery_charge_inside' => 60,
            'delivery_charge_outside' => 120,
        ]);

        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('Delivery Item', 'DELIVERY-001');
        app(StorefrontCart::class)->add($company, $product, 1);

        $this->post('http://delivery.example.test/checkout', [
            'name' => 'Outside Buyer',
            'phone' => '01712345678',
            'address' => 'Rangpur',
            'delivery_area' => 'outside',
            'payment_method' => 'cod',
        ])->assertRedirectContains('/checkout/success/');

        $order = Order::withoutGlobalScopes()->latest()->first();
        $this->assertSame('outside', $order->shipping_zone);
        $this->assertSame(120.0, (float) $order->shipping_fee);
    }

    public function test_manual_bkash_payment_creates_pending_verification_record(): void
    {
        $company = $this->createStore('bkash.example.test', [
            'manual_bkash_number' => '01700000000',
        ]);

        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('bKash Item', 'BKASH-001');
        app(StorefrontCart::class)->add($company, $product, 1);

        $this->post('http://bkash.example.test/checkout', [
            'name' => 'bKash Buyer',
            'phone' => '01711112222',
            'address' => 'Dhaka',
            'delivery_area' => 'inside',
            'payment_method' => 'manual_bkash',
            'sender_number' => '01799998888',
            'trx_id' => 'TRX12345',
        ])->assertRedirectContains('/checkout/success/');

        $payment = StorefrontPayment::withoutGlobalScopes()->first();
        $this->assertNotNull($payment);
        $this->assertSame('manual_bkash', $payment->gateway);
        $this->assertSame(StorefrontPayment::STATUS_PENDING, $payment->status);
        $this->assertSame('01799998888', $payment->payment_method);
        $this->assertSame('TRX12345', $payment->transaction_id);
    }

    public function test_manual_bkash_requires_sender_number_and_trx_id(): void
    {
        $company = $this->createStore('bkash-missing.example.test', [
            'manual_bkash_number' => '01700000000',
        ]);

        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('bKash Item 2', 'BKASH-002');
        app(StorefrontCart::class)->add($company, $product, 1);

        $this->post('http://bkash-missing.example.test/checkout', [
            'name' => 'bKash Buyer',
            'phone' => '01711113333',
            'address' => 'Dhaka',
            'delivery_area' => 'inside',
            'payment_method' => 'manual_bkash',
        ])->assertSessionHasErrors(['sender_number', 'trx_id']);

        $this->assertSame(0, Order::withoutGlobalScopes()->count());
    }

    public function test_admin_can_verify_a_pending_manual_payment(): void
    {
        $company = $this->createStore('verify.example.test', [
            'manual_nagad_number' => '01700000001',
        ]);

        $payment = StorefrontPayment::query()->create([
            'company_id' => $company->getKey(),
            'order_id' => Order::query()->create([
                'company_id' => $company->getKey(),
                'customer_name' => 'Verify Buyer',
                'status' => 'draft',
                'source' => Order::SOURCE_STOREFRONT,
            ])->getKey(),
            'gateway' => 'manual_nagad',
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
        ], $settingOverrides));

        return $company;
    }
}
