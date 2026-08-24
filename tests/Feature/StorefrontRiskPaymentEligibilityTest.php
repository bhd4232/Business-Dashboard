<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\StorefrontCheckoutAttempt;
use App\Models\StorefrontPayment;
use App\Models\StorefrontSetting;
use App\Services\CompanyContext;
use App\Services\ExternalCourierFraudService;
use App\Services\StorefrontCart;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class StorefrontRiskPaymentEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_low_courier_success_ratio_requires_configured_advance_without_creating_order(): void
    {
        [$company, $customer] = $this->store('risk-low.example.test', [
            'risk_payment_enabled' => true,
            'risk_payment_success_ratio_threshold' => 70,
            'risk_payment_advance_type' => StorefrontSetting::RISK_ADVANCE_PERCENT,
            'risk_payment_advance_percent' => 25,
            'online_payment_enabled' => true,
            'payment_credentials' => ['zinipay_api_key' => 'test_key'],
        ]);
        $this->fakeCourierResult([
            'pathao' => ['success' => 2, 'cancel' => 3, 'total' => 5],
            'overall_success_ratio' => 40,
        ]);
        $this->addProduct($company);
        Http::fake([
            'api.zinipay.com/v1/payment/create' => Http::response([
                'payment_url' => 'https://pay.zinipay.com/invoice/RISK-LOW-1',
            ]),
        ]);

        $this->post('http://risk-low.example.test/checkout', $this->checkoutData($customer))
            ->assertRedirect('https://pay.zinipay.com/invoice/RISK-LOW-1');

        $this->assertSame(0, Order::withoutGlobalScopes()->count());
        $payment = StorefrontPayment::withoutGlobalScopes()->sole();
        $this->assertSame(StorefrontPayment::PURPOSE_CHECKOUT_ADVANCE, $payment->purpose);
        $this->assertSame(267.5, (float) $payment->amount); // 25% of ৳ 1,070 including delivery.
        $attempt = StorefrontCheckoutAttempt::withoutGlobalScopes()->sole();
        $this->assertSame(40.0, (float) $attempt->courier_success_ratio);
        $this->assertSame(267.5, (float) $attempt->required_advance);
        $this->assertArrayHasKey('courier_low_success_ratio', $attempt->advance_reasons);
        $this->assertSame(StorefrontCheckoutAttempt::OUTCOME_PENDING_PAYMENT, $attempt->outcome);
    }

    public function test_unavailable_courier_providers_fail_open_to_normal_checkout(): void
    {
        [$company, $customer] = $this->store('risk-open.example.test', ['risk_payment_enabled' => true]);
        $this->fakeCourierResult(['overall_success_ratio' => null], 2);
        $this->addProduct($company);

        $this->post('http://risk-open.example.test/checkout', $this->checkoutData($customer))
            ->assertRedirectContains('/checkout/success/');

        $this->assertSame(1, Order::withoutGlobalScopes()->count());
        $this->assertSame(0, StorefrontPayment::withoutGlobalScopes()->count());
        $attempt = StorefrontCheckoutAttempt::withoutGlobalScopes()->sole();
        $this->assertSame(0.0, (float) $attempt->required_advance);
        $this->assertNull($attempt->courier_success_ratio);
    }

    public function test_reported_zero_history_can_require_fixed_advance(): void
    {
        [$company, $customer] = $this->store('risk-zero.example.test', [
            'risk_payment_enabled' => true,
            'risk_payment_zero_history_action' => StorefrontSetting::RISK_ZERO_HISTORY_REQUIRE_ADVANCE,
            'risk_payment_advance_type' => StorefrontSetting::RISK_ADVANCE_FIXED,
            'risk_payment_advance_amount' => 120,
            'online_payment_enabled' => true,
            'payment_credentials' => ['zinipay_api_key' => 'test_key'],
        ]);
        $this->fakeCourierResult([
            'steadfast' => ['success' => 0, 'cancel' => 0, 'total' => 0],
            'overall_success_ratio' => null,
        ]);
        $this->addProduct($company);
        Http::fake([
            'api.zinipay.com/v1/payment/create' => Http::response([
                'payment_url' => 'https://pay.zinipay.com/invoice/RISK-ZERO-1',
            ]),
        ]);

        $this->post('http://risk-zero.example.test/checkout', $this->checkoutData($customer))
            ->assertRedirect('https://pay.zinipay.com/invoice/RISK-ZERO-1');

        $payment = StorefrontPayment::withoutGlobalScopes()->sole();
        $this->assertSame(120.0, (float) $payment->amount);
        $this->assertArrayHasKey(
            'courier_no_history',
            StorefrontCheckoutAttempt::withoutGlobalScopes()->sole()->advance_reasons,
        );
    }

    private function fakeCourierResult(array $result, int $times = 1): void
    {
        $fraud = Mockery::mock(ExternalCourierFraudService::class);
        $fraud->shouldReceive('checkByPhone')->times($times)->andReturn($result);
        $this->app->instance(ExternalCourierFraudService::class, $fraud);
    }

    /** @return array{Company, Customer} */
    private function store(string $domain, array $overrides = []): array
    {
        $company = Company::query()->create([
            'name' => 'Store '.$domain,
            'slug' => str($domain)->slug()->toString(),
            'domain' => $domain,
            'domain_verified' => true,
            'invoice_prefix' => 'RSK',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);
        StorefrontSetting::query()->create(array_merge([
            'company_id' => $company->getKey(),
            'theme_color' => '#0F766E',
            'is_published' => true,
            'cod_enabled' => true,
            'new_customer_delivery_advance_enabled' => false,
            'delivery_first_kg_inside' => 70,
            'delivery_first_kg_outside' => 110,
            'delivery_additional_per_kg' => 20,
        ], $overrides));
        $customer = Customer::query()->create([
            'name' => 'Known Buyer',
            'phone' => '01710000999',
            'address' => 'Dhaka',
            'customer_type' => 'regular',
            'customer_source' => 'website',
            'opening_balance' => 0,
            'is_active' => true,
        ]);

        return [$company, $customer];
    }

    private function addProduct(Company $company): void
    {
        $product = Product::query()->create([
            'name' => 'Risk Checked Item',
            'sku' => 'RISK-ITEM',
            'price' => 1000,
            'sale_price' => 1000,
            'cost_price' => 600,
            'stock' => 10,
            'unit' => 'pcs',
            'weight_kg' => 1,
            'reorder_level' => 2,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);
        app(StorefrontCart::class)->add($company, $product, 1);
    }

    private function checkoutData(Customer $customer): array
    {
        return [
            'name' => $customer->name,
            'phone' => $customer->phone,
            'address' => $customer->address,
            'payment_method' => 'cod',
        ];
    }
}
