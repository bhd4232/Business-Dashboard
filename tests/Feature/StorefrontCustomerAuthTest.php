<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\StorefrontSetting;
use App\Services\CompanyContext;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StorefrontCustomerAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_customer_can_register_log_in_and_log_out(): void
    {
        $company = $this->createPublishedStorefrontCompany('Auth Store', 'auth.example.test');

        $this->post('http://auth.example.test/account/register', [
            'name' => 'New Buyer',
            'phone' => '01711111111',
            'email' => 'buyer@example.test',
            'password' => 'super-secret',
            'password_confirmation' => 'super-secret',
        ])->assertRedirect('http://auth.example.test/account/profile');

        $customer = Customer::query()->where('phone', '01711111111')->first();
        $this->assertNotNull($customer);
        $this->assertTrue($customer->isRegistered());
        $this->assertTrue(Hash::check('super-secret', $customer->password));
        $this->assertAuthenticatedAs($customer, 'customer');

        $this->post('http://auth.example.test/account/logout')
            ->assertRedirect('http://auth.example.test');
        $this->assertGuest('customer');

        $this->get('http://auth.example.test/account/profile')
            ->assertRedirect('http://auth.example.test/account/login');

        $this->post('http://auth.example.test/account/login', [
            'identifier' => '01711111111',
            'password' => 'super-secret',
        ])->assertRedirect('http://auth.example.test/account/profile');
        $this->assertAuthenticatedAs($customer->fresh(), 'customer');

        $this->post('http://auth.example.test/account/logout');

        $this->post('http://auth.example.test/account/login', [
            'identifier' => 'buyer@example.test',
            'password' => 'super-secret',
        ])->assertRedirect('http://auth.example.test/account/profile');
        $this->assertAuthenticatedAs($customer->fresh(), 'customer');
    }

    public function test_login_rejects_wrong_password(): void
    {
        $this->createPublishedStorefrontCompany('Wrong Password Store', 'wrongpass.example.test');

        $this->post('http://wrongpass.example.test/account/register', [
            'name' => 'Careful Buyer',
            'phone' => '01722222222',
            'password' => 'correct-password',
            'password_confirmation' => 'correct-password',
        ]);
        $this->post('http://wrongpass.example.test/account/logout');

        $this->post('http://wrongpass.example.test/account/login', [
            'identifier' => '01722222222',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('identifier');
        $this->assertGuest('customer');
    }

    public function test_registration_reuses_existing_unregistered_customer_record(): void
    {
        $company = $this->createPublishedStorefrontCompany('Reuse Store', 'reuse.example.test');

        app(CompanyContext::class)->set($company);
        $existing = Customer::query()->create([
            'name' => 'Walk In',
            'phone' => '01733333333',
            'is_active' => true,
        ]);

        $this->post('http://reuse.example.test/account/register', [
            'name' => 'Walk In Registered',
            'phone' => '01733333333',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect('http://reuse.example.test/account/profile');

        $this->assertSame(1, Customer::query()->where('phone', '01733333333')->count());
        $this->assertSame($existing->getKey(), Customer::query()->where('phone', '01733333333')->first()->getKey());
    }

    public function test_registration_blocks_duplicate_phone(): void
    {
        $this->createPublishedStorefrontCompany('Duplicate Store', 'duplicate.example.test');

        $this->post('http://duplicate.example.test/account/register', [
            'name' => 'First Buyer',
            'phone' => '01744444444',
            'password' => 'first-password',
            'password_confirmation' => 'first-password',
        ]);
        $this->post('http://duplicate.example.test/account/logout');

        $this->post('http://duplicate.example.test/account/register', [
            'name' => 'Second Buyer',
            'phone' => '01744444444',
            'password' => 'second-password',
            'password_confirmation' => 'second-password',
        ])->assertSessionHasErrors('phone');
    }

    public function test_profile_update_and_password_change(): void
    {
        $this->createPublishedStorefrontCompany('Profile Store', 'profile.example.test');

        $this->post('http://profile.example.test/account/register', [
            'name' => 'Profile Buyer',
            'phone' => '01755555555',
            'password' => 'original-pass',
            'password_confirmation' => 'original-pass',
        ]);

        $this->patch('http://profile.example.test/account/profile', [
            'name' => 'Updated Name',
            'email' => 'updated@example.test',
            'address' => 'New address line',
        ])->assertRedirect('http://profile.example.test/account/profile');

        $customer = Customer::query()->where('phone', '01755555555')->first();
        $this->assertSame('Updated Name', $customer->name);
        $this->assertSame('updated@example.test', $customer->email);

        $this->put('http://profile.example.test/account/password', [
            'current_password' => 'wrong-current',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertSessionHasErrors('current_password');

        $this->put('http://profile.example.test/account/password', [
            'current_password' => 'original-pass',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect('http://profile.example.test/account/profile');

        $this->assertTrue(Hash::check('brand-new-pass', $customer->fresh()->password));
    }

    public function test_forgot_and_reset_password_via_sms_code(): void
    {
        $company = $this->createPublishedStorefrontCompany('Reset Store', 'reset.example.test', [
            'notification_credentials' => [
                'sms_api_url' => 'http://sms.example.test/send?key={api_key}&to={phone}&msg={message}',
                'sms_api_key' => 'test-key',
                'sms_sender_id' => 'TESTID',
            ],
        ]);

        $this->post('http://reset.example.test/account/register', [
            'name' => 'Reset Buyer',
            'phone' => '01766666666',
            'password' => 'old-password',
            'password_confirmation' => 'old-password',
        ]);
        $this->post('http://reset.example.test/account/logout');

        Http::fake(['sms.example.test/*' => Http::response('OK', 200)]);

        $this->post('http://reset.example.test/account/forgot-password', [
            'phone' => '01766666666',
        ])->assertRedirect('http://reset.example.test/account/reset-password?phone=01766666666');

        $sentUrl = null;
        Http::assertSent(function ($request) use (&$sentUrl): bool {
            $sentUrl = $request->url();

            return true;
        });

        $this->assertNotNull($sentUrl);
        preg_match('/reset code is (\d{6})/', urldecode($sentUrl), $matches);
        $this->assertNotEmpty($matches, 'Reset code was not found in the sent SMS message.');
        $code = $matches[1];

        $this->post('http://reset.example.test/account/reset-password', [
            'phone' => '01766666666',
            'code' => '000000',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertSessionHasErrors('code');

        $this->post('http://reset.example.test/account/reset-password', [
            'phone' => '01766666666',
            'code' => $code,
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect('http://reset.example.test/account/profile');

        $customer = Customer::query()->where('phone', '01766666666')->first();
        $this->assertTrue(Hash::check('brand-new-pass', $customer->password));
    }

    public function test_forgot_password_reports_unavailable_when_sms_not_configured(): void
    {
        $this->createPublishedStorefrontCompany('No SMS Store', 'nosms.example.test');

        Http::fake();

        $this->post('http://nosms.example.test/account/forgot-password', [
            'phone' => '01777777777',
        ])->assertSessionHas('storefront_status', fn (string $message): bool => str_contains($message, "isn't available"));

        Http::assertNothingSent();
    }

    public function test_customer_accounts_can_be_disabled_per_company(): void
    {
        $this->createPublishedStorefrontCompany('Disabled Accounts Store', 'disabled.example.test', [
            'customer_accounts_enabled' => false,
        ]);

        $this->get('http://disabled.example.test/account/login')->assertNotFound();
        $this->get('http://disabled.example.test/account/register')->assertNotFound();
        $this->get('http://disabled.example.test/account/orders')
            ->assertRedirect('http://disabled.example.test/track');
    }

    public function test_customer_accounts_are_isolated_per_company(): void
    {
        $this->createPublishedStorefrontCompany('Isolation Store A', 'isolation-a.example.test');
        $this->createPublishedStorefrontCompany('Isolation Store B', 'isolation-b.example.test');

        $this->post('http://isolation-a.example.test/account/register', [
            'name' => 'Company A Buyer',
            'phone' => '01788888888',
            'password' => 'shared-password',
            'password_confirmation' => 'shared-password',
        ])->assertRedirect('http://isolation-a.example.test/account/profile');
        $this->post('http://isolation-a.example.test/account/logout');

        $this->post('http://isolation-b.example.test/account/login', [
            'identifier' => '01788888888',
            'password' => 'shared-password',
        ])->assertSessionHasErrors('identifier');
        $this->assertGuest('customer');
    }

    public function test_product_search_filters_by_name(): void
    {
        $company = $this->createPublishedStorefrontCompany('Search Store', 'search.example.test');
        app(CompanyContext::class)->set($company);

        $this->createProduct('Blue Widget', 'SEARCH-BLUE-001');
        $this->createProduct('Red Gadget', 'SEARCH-RED-001');

        $this->get('http://search.example.test/products?q=widget')
            ->assertOk()
            ->assertSee('Blue Widget')
            ->assertDontSee('Red Gadget');
    }

    private function createProduct(string $name, string $sku): Product
    {
        return Product::query()->create([
            'name' => $name,
            'sku' => $sku,
            'price' => 1000,
            'sale_price' => 900,
            'cost_price' => 600,
            'stock' => 10,
            'unit' => 'pcs',
            'reorder_level' => 2,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);
    }

    private function createPublishedStorefrontCompany(string $name, string $domain, array $settingOverrides = []): Company
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString().'-'.str($domain)->slug()->toString(),
            'domain' => $domain,
            'domain_verified' => true,
            'invoice_prefix' => str($domain)->slug('')->substr(0, 12)->upper()->toString(),
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        StorefrontSetting::query()->create(array_merge([
            'company_id' => $company->getKey(),
            'theme_color' => '#0F766E',
            'whatsapp_number' => '+8801700000000',
            'meta_title' => $name,
            'is_published' => true,
        ], $settingOverrides));

        return $company;
    }
}
