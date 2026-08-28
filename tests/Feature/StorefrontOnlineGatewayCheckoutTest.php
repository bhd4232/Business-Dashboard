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
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers the new "pay the full order online" checkout option (the
 * `online_gateway` StorefrontPaymentMethod row) - a normal customer choice
 * that reuses whichever gateway/credentials are already configured on
 * StorefrontSetting, orthogonal to the pre-existing mandatory advance rules
 * (preorder/new-customer/courier-risk) covered by the other Storefront*
 * checkout test files.
 */
class StorefrontOnlineGatewayCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_customer_can_pay_the_full_order_online_and_the_order_is_created_fully_paid_after_verification(): void
    {
        $company = $this->createStore('pay-online.example.test');
        $this->activateOnlineGatewayMethod($company);

        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('Online Pay Item', 'ONLINE-PAY-01', 1000);
        app(StorefrontCart::class)->add($company, $product, 1);

        Http::fake([
            'api.paystation.com.bd/grant-token' => Http::response(['status_code' => '200', 'status' => 'success', 'token' => 'tok-abc']),
            'api.paystation.com.bd/create-payment' => Http::response([
                'status_code' => 200,
                'status' => 'success',
                'payment_url' => 'https://pay.paystation.com.bd/checkout/full-amount',
            ]),
        ]);

        $response = $this->post('http://pay-online.example.test/checkout', [
            'name' => 'Full Payer',
            'phone' => '01712340001',
            'address' => 'Dhaka',
            'payment_method' => StorefrontPaymentMethod::TYPE_ONLINE_GATEWAY,
        ]);

        $response->assertRedirect('https://pay.paystation.com.bd/checkout/full-amount');

        $payment = StorefrontPayment::withoutGlobalScopes()->sole();
        // Product price 1000 + the default first-1kg-inside-Dhaka delivery
        // fee (70) = the full order total, not a partial advance.
        $this->assertSame(1070.0, (float) $payment->amount);
        $this->assertSame(StorefrontPayment::PURPOSE_CHECKOUT_ADVANCE, $payment->purpose);
        $this->assertSame('paystation', $payment->gateway);
        $this->assertSame(0, Order::withoutGlobalScopes()->count());

        $capturedCallbackUrl = null;
        $capturedInvoiceNumber = null;
        Http::assertSent(function ($request) use (&$capturedCallbackUrl, &$capturedInvoiceNumber): bool {
            if (! str_contains($request->url(), '/create-payment')) {
                return false;
            }

            $capturedCallbackUrl = (string) $request['callback_url'];
            $capturedInvoiceNumber = (string) $request['invoice_number'];

            return true;
        });

        Http::fake([
            'api.paystation.com.bd/grant-token' => Http::response(['token' => 'tok-abc']),
            'api.paystation.com.bd/retrive-transaction' => Http::response([
                'status_code' => 200,
                'status' => 'success',
                'trx_status' => 'Success',
                'payment_amount' => '1070',
                'trx_id' => 'PS-FULL-1',
                'payment_method' => 'bkash',
            ]),
        ]);

        // Mirrors PayStation's real redirect: it appends invoice_number/trx_id
        // onto the callback_url we sent (see StorefrontPaystationPaymentTest).
        $returnUrl = $capturedCallbackUrl.'&invoice_number='.$capturedInvoiceNumber.'&trx_id=PS-FULL-1';

        $this->get($returnUrl)->assertRedirectContains('/checkout/success/');

        $order = Order::withoutGlobalScopes()->sole();
        $this->assertSame(1070.0, (float) $order->paid_amount);
        $this->assertSame(1070.0, (float) $order->total_amount);
        $this->assertStringContainsString('Paid online in full', $order->note);
    }

    public function test_online_gateway_option_is_unavailable_when_its_payment_method_row_is_not_active(): void
    {
        // Matches the create_storefront_payment_methods_table migration's
        // default: the online_gateway row exists but is inactive until an
        // admin opts in, even though the gateway itself is configured.
        $company = $this->createStore('pay-online-inactive.example.test');

        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('Inactive Online Item', 'ONLINE-PAY-02', 500);
        app(StorefrontCart::class)->add($company, $product, 1);

        $this->post('http://pay-online-inactive.example.test/checkout', [
            'name' => 'Blocked Payer',
            'phone' => '01712340002',
            'address' => 'Dhaka',
            'payment_method' => StorefrontPaymentMethod::TYPE_ONLINE_GATEWAY,
        ])->assertSessionHasErrors('payment_method');

        $this->assertSame(0, StorefrontPayment::withoutGlobalScopes()->count());
        $this->assertSame(0, Order::withoutGlobalScopes()->count());
    }

    public function test_paying_online_in_full_takes_precedence_over_the_smaller_mandatory_new_customer_advance(): void
    {
        $company = $this->createStore('pay-online-precedence.example.test', [
            'new_customer_delivery_advance_enabled' => true,
        ]);
        $this->activateOnlineGatewayMethod($company);

        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('Precedence Item', 'ONLINE-PAY-03', 2000);
        app(StorefrontCart::class)->add($company, $product, 1);

        Http::fake([
            'api.paystation.com.bd/grant-token' => Http::response(['status_code' => '200', 'status' => 'success', 'token' => 'tok-abc']),
            'api.paystation.com.bd/create-payment' => Http::response([
                'status_code' => 200,
                'status' => 'success',
                'payment_url' => 'https://pay.paystation.com.bd/checkout/precedence',
            ]),
        ]);

        // This phone has never ordered before, so the new-customer
        // delivery-advance rule would normally only require the ~70 tk
        // delivery fee up front. Choosing to pay online in full must still
        // charge the FULL 2070, not just that smaller mandatory advance.
        $this->post('http://pay-online-precedence.example.test/checkout', [
            'name' => 'New Customer Full Payer',
            'phone' => '01712340003',
            'address' => 'Dhaka',
            'payment_method' => StorefrontPaymentMethod::TYPE_ONLINE_GATEWAY,
        ])->assertRedirect('https://pay.paystation.com.bd/checkout/precedence');

        $payment = StorefrontPayment::withoutGlobalScopes()->sole();
        $this->assertSame(2070.0, (float) $payment->amount);
    }

    private function activateOnlineGatewayMethod(Company $company): StorefrontPaymentMethod
    {
        return StorefrontPaymentMethod::withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $company->getKey(), 'type' => StorefrontPaymentMethod::TYPE_ONLINE_GATEWAY],
            ['name' => 'Pay Online (Card/Mobile Banking)', 'is_active' => true, 'sort_order' => 1],
        );
    }

    private function createProduct(string $name, string $sku, float $price): Product
    {
        return Product::query()->create([
            'name' => $name,
            'sku' => $sku,
            'price' => $price + 100,
            'sale_price' => $price,
            'cost_price' => $price * 0.6,
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
            'invoice_prefix' => str($domain)->slug()->substr(0, 4)->upper()->toString(),
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        StorefrontSetting::query()->create(array_merge([
            'company_id' => $company->getKey(),
            'theme_color' => '#0F766E',
            'is_published' => true,
            'new_customer_delivery_advance_enabled' => false,
            'online_payment_enabled' => true,
            'online_payment_gateway' => 'paystation',
            'payment_credentials' => ['paystation_merchant_id' => 'test-mid', 'paystation_password' => 'test-pass'],
        ], $settingOverrides));

        return $company;
    }
}
