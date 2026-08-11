<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerBlacklist;
use App\Models\Order;
use App\Models\Product;
use App\Models\StorefrontCheckoutAttempt;
use App\Models\StorefrontSetting;
use App\Models\User;
use App\Services\CompanyContext;
use App\Services\StorefrontCart;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCheckoutPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_enforcement_blocks_invalid_bangladesh_phone_and_audits_masked_identity(): void
    {
        [$company, $product] = $this->store('policy-phone.example.test');
        $this->addToCart($company, $product);

        $this->post('http://policy-phone.example.test/checkout', $this->checkoutData(phone: '12345'))
            ->assertSessionHasErrors('phone');

        $this->assertSame(0, Order::withoutGlobalScopes()->count());
        $attempt = StorefrontCheckoutAttempt::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(StorefrontCheckoutAttempt::OUTCOME_BLOCKED, $attempt->outcome);
        $this->assertArrayHasKey('invalid_phone', $attempt->violations);
        $this->assertNull($attempt->phone_mask);
    }

    public function test_global_email_blacklist_blocks_checkout_without_exposing_the_reason(): void
    {
        [$company, $product] = $this->store('policy-email.example.test');
        CustomerBlacklist::query()->create([
            'email' => 'blocked@example.com',
            'reason' => 'Confirmed abuse.',
            'is_active' => true,
        ]);
        $this->addToCart($company, $product);

        $response = $this->post('http://policy-email.example.test/checkout', $this->checkoutData(email: 'BLOCKED@EXAMPLE.COM'));

        $response->assertSessionHasErrors('checkout');
        $this->assertStringNotContainsString('Confirmed abuse', (string) session('errors')?->first('checkout'));
        $this->assertArrayHasKey('blocked_email', StorefrontCheckoutAttempt::withoutGlobalScopes()->firstOrFail()->violations);
    }

    public function test_observe_mode_records_violation_but_allows_and_normalizes_checkout(): void
    {
        [$company, $product, $setting] = $this->store('policy-observe.example.test');
        $setting->update(['checkout_policy_mode' => StorefrontSetting::CHECKOUT_POLICY_OBSERVE]);
        CustomerBlacklist::query()->create([
            'phone' => '+8801712345678',
            'reason' => 'Observe this customer.',
            'is_active' => true,
        ]);
        $this->addToCart($company, $product);

        $this->post('http://policy-observe.example.test/checkout', $this->checkoutData(phone: '+8801712345678'))
            ->assertRedirectContains('/checkout/success/');

        $customer = Customer::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('01712345678', $customer->phone);
        $attempt = StorefrontCheckoutAttempt::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(StorefrontCheckoutAttempt::OUTCOME_ACCEPTED, $attempt->outcome);
        $this->assertArrayHasKey('blocked_phone', $attempt->violations);
        $this->assertNotNull($attempt->order_id);
    }

    public function test_recent_order_limit_blocks_a_second_storefront_order(): void
    {
        [$company, $product] = $this->store('policy-limit.example.test', [
            'checkout_order_limit_enabled' => true,
            'checkout_order_limit_count' => 1,
            'checkout_order_limit_hours' => 24,
        ]);

        $this->addToCart($company, $product);
        $this->post('http://policy-limit.example.test/checkout', $this->checkoutData())
            ->assertRedirectContains('/checkout/success/');

        $this->addToCart($company, $product);
        $this->post('http://policy-limit.example.test/checkout', $this->checkoutData())
            ->assertSessionHasErrors('checkout');

        $this->assertSame(1, Order::withoutGlobalScopes()->count());
        $blocked = StorefrontCheckoutAttempt::withoutGlobalScopes()->latest('id')->firstOrFail();
        $this->assertSame(StorefrontCheckoutAttempt::OUTCOME_BLOCKED, $blocked->outcome);
        $this->assertArrayHasKey('phone_order_limit', $blocked->violations);
        $this->assertArrayHasKey('ip_order_limit', $blocked->violations);
    }

    public function test_local_checkout_reuses_an_existing_e164_customer(): void
    {
        [$company, $product] = $this->store('policy-existing.example.test');
        $customer = Customer::query()->create([
            'name' => 'Existing Buyer',
            'phone' => '+8801712345678',
            'address' => 'Dhaka',
            'opening_balance' => 0,
            'is_active' => true,
        ]);
        $this->addToCart($company, $product);

        $this->post('http://policy-existing.example.test/checkout', $this->checkoutData())
            ->assertRedirectContains('/checkout/success/');

        $this->assertSame(1, Customer::withoutGlobalScopes()->count());
        $this->assertSame($customer->getKey(), Order::withoutGlobalScopes()->sole()->customer_id);
    }

    public function test_checkout_attempts_render_in_the_read_only_filament_resource(): void
    {
        [$company, $product] = $this->store('policy-admin.example.test');
        $this->addToCart($company, $product);
        $this->post('http://policy-admin.example.test/checkout', $this->checkoutData(phone: 'bad-phone'));
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->getKey()])
            ->get(route('filament.admin.storefront.resources.storefront-checkout-attempts.index'))
            ->assertOk()
            ->assertSee('Checkout Attempts')
            ->assertSee('blocked');
    }

    /** @return array{Company, Product, StorefrontSetting} */
    protected function store(string $domain, array $settings = []): array
    {
        $company = Company::query()->create([
            'name' => 'Store '.$domain,
            'slug' => str($domain)->slug()->toString(),
            'domain' => $domain,
            'domain_verified' => true,
            'invoice_prefix' => 'POL',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);

        $setting = StorefrontSetting::query()->create(array_merge([
            'company_id' => $company->getKey(),
            'theme_color' => '#0F766E',
            'is_published' => true,
            'cod_enabled' => true,
            'new_customer_delivery_advance_enabled' => false,
            'checkout_policy_mode' => StorefrontSetting::CHECKOUT_POLICY_ENFORCE,
            'checkout_validate_bd_phone' => true,
            'checkout_block_phone' => true,
            'checkout_block_email' => true,
            'checkout_block_ip' => true,
            'checkout_order_limit_enabled' => false,
            'checkout_policy_contact_phone' => '01700000000',
        ], $settings));

        $product = Product::query()->create([
            'name' => 'Policy Product',
            'sku' => 'POLICY-'.str()->random(6),
            'price' => 500,
            'sale_price' => 450,
            'cost_price' => 200,
            'stock' => 10,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);

        return [$company, $product, $setting];
    }

    protected function addToCart(Company $company, Product $product): void
    {
        app(CompanyContext::class)->set($company);
        app(StorefrontCart::class)->add($company, $product, 1);
    }

    protected function checkoutData(string $phone = '01712345678', string $email = 'buyer@example.com'): array
    {
        return [
            'name' => 'Policy Buyer',
            'phone' => $phone,
            'email' => $email,
            'address' => 'Mirpur, Dhaka',
            'payment_method' => 'cod',
        ];
    }
}
