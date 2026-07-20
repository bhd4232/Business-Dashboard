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
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StorefrontPreorderPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_preorder_product_can_be_added_beyond_stock_and_shows_preorder_button(): void
    {
        $company = $this->createStore('preorder.example.test');

        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('Preorder Router', 'PRE-ROUTER-01', [
            'stock' => 0,
            'is_preorder' => true,
            'preorder_advance_percent' => 30,
        ]);

        $this->get('http://preorder.example.test/product/'.$product->slug)
            ->assertOk()
            ->assertSee('Pre-order now')
            ->assertSee('advance payment of 30%');

        $cart = app(StorefrontCart::class);
        $cart->add($company, $product, 4);

        $this->assertSame(4, $cart->items($company)->first()['quantity']);
    }

    public function test_zero_stock_preorder_can_be_added_and_cart_quantity_matches_moq_and_preorder_ceiling(): void
    {
        $company = $this->createStore('preorder-cart.example.test');

        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('Preorder Cart Item', 'PRE-CART-01', [
            'stock' => 0,
            'moq' => 4,
            'is_preorder' => true,
        ]);

        $this->post('http://preorder-cart.example.test/cart/items/'.$product->slug, [
            'quantity' => 1,
        ])->assertRedirect();

        $cart = app(StorefrontCart::class);
        $this->assertSame(4, $cart->items($company)->first()['quantity']);

        $this->get('http://preorder-cart.example.test/cart')
            ->assertOk()
            ->assertSee('Available for pre-order')
            ->assertSee('name="quantity" min="4" max="'.StorefrontCart::PREORDER_STOCK_CEILING.'" value="4"', false)
            ->assertDontSee('0 in stock');

        $this->patch('http://preorder-cart.example.test/cart/items/'.$product->slug, [
            'quantity' => StorefrontCart::PREORDER_STOCK_CEILING + 1,
        ])->assertRedirect();

        $this->assertSame(
            StorefrontCart::PREORDER_STOCK_CEILING,
            $cart->items($company)->first()['quantity'],
        );
    }

    public function test_preorder_checkout_creates_payment_and_redirects_to_gateway(): void
    {
        $company = $this->createStore('pay.example.test', zinipay: true);

        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('Preorder Panel', 'PRE-PANEL-01', [
            'stock' => 0,
            'is_preorder' => true,
            'preorder_advance_percent' => 50,
            'sale_price' => 1000,
        ]);

        Http::fake([
            'api.zinipay.com/v1/payment/create' => Http::response([
                'status' => true,
                'message' => 'ok',
                'payment_url' => 'https://pay.zinipay.com/invoice/INV-12345',
            ]),
        ]);

        app(StorefrontCart::class)->add($company, $product, 2);

        $response = $this->post('http://pay.example.test/checkout', [
            'name' => 'Preorder Buyer',
            'phone' => '01712341234',
            'address' => 'Dhaka',
        ]);

        $response->assertRedirect('https://pay.zinipay.com/invoice/INV-12345');

        Http::assertSent(function ($request): bool {
            $redirectUrl = (string) $request['redirect_url'];
            $cancelUrl = (string) $request['cancel_url'];

            return str_contains($request->url(), '/v1/payment/create')
                && $redirectUrl === $cancelUrl
                && str_contains($redirectUrl, '/checkout/success/')
                && str_contains($redirectUrl, 'expires=')
                && str_contains($redirectUrl, 'signature=');
        });

        $payment = StorefrontPayment::withoutGlobalScopes()->first();
        $this->assertNotNull($payment);
        $this->assertSame(1000.0, (float) $payment->amount); // 2 x 1000 x 50%
        $this->assertSame('pending', $payment->status);
        $this->assertSame('INV-12345', $payment->invoice_id);
        $this->assertSame($company->getKey(), $payment->company_id);

        $order = Order::withoutGlobalScopes()->find($payment->order_id);
        $this->assertSame(Order::SOURCE_STOREFRONT, $order->source);
    }

    public function test_preorder_checkout_blocked_when_online_payment_unavailable(): void
    {
        $company = $this->createStore('nopay.example.test', zinipay: false);

        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('Blocked Preorder', 'PRE-BLOCK-01', [
            'stock' => 0,
            'is_preorder' => true,
        ]);

        app(StorefrontCart::class)->add($company, $product, 1);

        $checkout = $this->get('http://nopay.example.test/checkout')
            ->assertOk()
            ->assertSee('Online payment is currently unavailable.');
        $this->assertMatchesRegularExpression(
            '/<button(?=[^>]*data-checkout-submit)(?=[^>]*disabled)[^>]*>/',
            $checkout->getContent(),
        );

        $this->post('http://nopay.example.test/checkout', [
            'name' => 'Blocked Buyer',
            'phone' => '01712340000',
            'address' => 'Dhaka',
        ])->assertSessionHasErrors('payment');

        $this->get('http://nopay.example.test/checkout')
            ->assertOk()
            ->assertSee('Pre-order items require an online advance payment')
            ->assertSee('id="checkout-payment-errors"', false);

        $this->assertSame(0, Order::withoutGlobalScopes()->count());
    }

    public function test_instock_checkout_stays_cod_without_payment(): void
    {
        $company = $this->createStore('cod.example.test', zinipay: true);

        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('Stocked Item', 'COD-ITEM-01', ['stock' => 10]);

        app(StorefrontCart::class)->add($company, $product, 2);

        $this->post('http://cod.example.test/checkout', [
            'name' => 'COD Buyer',
            'phone' => '01712345678',
            'address' => 'Dhaka',
        ])->assertRedirectContains('/checkout/success/');

        $this->assertSame(0, StorefrontPayment::withoutGlobalScopes()->count());
    }

    public function test_zinipay_webhook_marks_payment_completed_after_verification(): void
    {
        $company = $this->createStore('hook.example.test', zinipay: true);

        app(CompanyContext::class)->set($company);

        $order = Order::query()->create([
            'customer_name' => 'Hook Buyer',
            'status' => 'draft',
            'source' => Order::SOURCE_STOREFRONT,
        ]);

        $payment = StorefrontPayment::query()->create([
            'company_id' => $company->getKey(),
            'order_id' => $order->getKey(),
            'gateway' => 'zinipay',
            'invoice_id' => 'INV-HOOK-1',
            'amount' => 500,
            'status' => StorefrontPayment::STATUS_PENDING,
        ]);

        Http::fake([
            'api.zinipay.com/v1/payment/verify' => Http::response([
                'amount' => 500,
                'invoice_id' => 'INV-HOOK-1',
                'payment_method' => 'bkash',
                'transaction_id' => 'TRX999',
                'status' => 'COMPLETED',
            ]),
        ]);

        $this->postJson('http://hook.example.test/webhooks/zinipay/'.$payment->getKey(), [
            'invoice_id' => 'INV-HOOK-1',
            'status' => 'true',
        ])->assertOk();

        $payment->refresh();
        $this->assertSame(StorefrontPayment::STATUS_COMPLETED, $payment->status);
        $this->assertSame('TRX999', $payment->transaction_id);
        $this->assertSame('bkash', $payment->payment_method);
    }

    private function createProduct(string $name, string $sku, array $overrides = []): Product
    {
        return Product::query()->create(array_merge([
            'name' => $name,
            'sku' => $sku,
            'price' => 1100,
            'sale_price' => 1000,
            'cost_price' => 600,
            'stock' => 10,
            'unit' => 'pcs',
            'reorder_level' => 2,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ], $overrides));
    }

    private function createStore(string $domain, bool $zinipay = false): Company
    {
        $company = Company::query()->create([
            'name' => 'Store '.$domain,
            'slug' => str($domain)->slug()->toString(),
            'domain' => $domain,
            'domain_verified' => true,
            'invoice_prefix' => 'PRE',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        StorefrontSetting::query()->create([
            'company_id' => $company->getKey(),
            'theme_color' => '#0F766E',
            'is_published' => true,
            'online_payment_enabled' => $zinipay,
            'payment_credentials' => $zinipay ? ['zinipay_api_key' => 'test_key'] : null,
        ]);

        return $company;
    }
}
