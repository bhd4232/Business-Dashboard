<?php

namespace Tests\Feature;

use App\Filament\Pages\Integrations;
use App\Models\Company;
use App\Models\CourierProvider;
use App\Models\MetaAdAccount;
use App\Models\StorefrontSetting;
use App\Models\User;
use App\Models\UserRole;
use App\Services\CompanyContext;
use App\Services\Crm\AiSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class IntegrationsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_save_every_tab_in_one_go_for_a_brand_new_company(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeSuperAdmin($company);
        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->getKey(), 'current_company_selection_explicit' => true]);

        // No StorefrontSetting row exists yet for this company — the required
        // "Active gateway" Select must still validate via an explicit
        // mount()-time default, not rely on the field's own ->default().
        Livewire::test(Integrations::class)
            // Any OpenAI-compatible provider (not just OpenAI itself) can be
            // added via the free-text label + base URL, without a code change.
            ->set('data.ai_api_format', 'openai')
            ->set('data.ai_provider', 'DeepSeek')
            ->set('data.ai_base_url', 'https://api.deepseek.com/chat/completions')
            ->set('data.ai_model', 'deepseek-chat')
            ->set('data.ai_confidence_threshold', 0.8)
            ->set('data.ai_max_consecutive_ai_replies', 3)
            ->set('data.ai_api_key', 'sk-live-test')
            ->set('data.woocommerce_base_url', 'https://shop.example.com')
            ->set('data.woocommerce_credentials.consumer_key', 'ck_1')
            ->set('data.woocommerce_credentials.consumer_secret', 'cs_1')
            ->set('data.online_payment_enabled', true)
            ->set('data.online_payment_gateway', 'zinipay')
            ->set('data.payment_credentials.zinipay_api_key', 'zk_1')
            ->set('data.meta_tracking_enabled', true)
            ->set('data.meta_pixel_id', '123456789')
            ->set('data.meta_capi_enabled', true)
            ->set('data.meta_tracking_credentials.access_token', 'tok_1')
            ->call('save')
            ->assertHasNoFormErrors();

        $ai = app(AiSettingsService::class)->all($company);
        $this->assertSame('deepseek-chat', $ai['model']);
        $this->assertSame('sk-live-test', $ai['api_key']);
        $this->assertSame('openai', $ai['api_format']);
        $this->assertSame('DeepSeek', $ai['provider']);
        $this->assertSame('https://api.deepseek.com/chat/completions', $ai['base_url']);

        $setting = StorefrontSetting::withoutGlobalScopes()->where('company_id', $company->getKey())->firstOrFail();
        $this->assertSame('https://shop.example.com', $setting->woocommerce_base_url);
        $this->assertSame('ck_1', $setting->woocommerce_credentials['consumer_key']);
        $this->assertTrue((bool) $setting->online_payment_enabled);
        $this->assertSame('zk_1', $setting->payment_credentials['zinipay_api_key']);
        $this->assertSame('123456789', $setting->meta_pixel_id);
        $this->assertSame('tok_1', $setting->meta_tracking_credentials['access_token']);
    }

    public function test_saving_never_touches_unrelated_storefront_settings_on_the_same_row(): void
    {
        $company = $this->makeCompany();
        StorefrontSetting::query()->create([
            'company_id' => $company->getKey(),
            'theme_color' => '#123456',
            'meta_title' => 'My Store Title',
            'is_published' => true,
            'cod_enabled' => true,
        ]);

        $user = $this->makeSuperAdmin($company);
        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->getKey(), 'current_company_selection_explicit' => true]);

        Livewire::test(Integrations::class)
            ->set('data.woocommerce_base_url', 'https://shop2.example.com')
            ->call('save')
            ->assertHasNoFormErrors();

        $setting = StorefrontSetting::withoutGlobalScopes()->where('company_id', $company->getKey())->firstOrFail();
        $this->assertSame('#123456', $setting->theme_color);
        $this->assertSame('My Store Title', $setting->meta_title);
        $this->assertTrue((bool) $setting->is_published);
        $this->assertSame('https://shop2.example.com', $setting->woocommerce_base_url);
    }

    public function test_a_non_super_admin_with_settings_permission_can_edit_woocommerce_but_never_ai(): void
    {
        $company = $this->makeCompany();
        UserRole::query()->create(['name' => 'Ops Manager', 'slug' => 'ops_manager', 'permissions' => ['settings.manage'], 'is_active' => true]);
        $user = User::factory()->create(['role' => 'ops_manager', 'is_active' => true]);
        $user->companies()->attach($company, ['role' => 'ops_manager', 'is_default' => true]);

        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->getKey(), 'current_company_selection_explicit' => true]);

        $this->assertTrue(Integrations::canAccess());

        // The AI fields are set anyway (simulating a crafted request bypassing
        // the hidden tab in the UI) — save() must still refuse to persist them.
        Livewire::test(Integrations::class)
            ->set('data.woocommerce_base_url', 'https://staff-shop.example.com')
            ->set('data.ai_api_format', 'openai')
            ->set('data.ai_provider', 'OpenAI')
            ->set('data.ai_model', 'gpt-should-not-save')
            ->set('data.ai_api_key', 'sk-should-not-save')
            ->call('save')
            ->assertHasNoFormErrors();

        $ai = app(AiSettingsService::class)->all($company);
        $this->assertBlank($ai['api_key'] ?? null);

        $setting = StorefrontSetting::withoutGlobalScopes()->where('company_id', $company->getKey())->firstOrFail();
        $this->assertSame('https://staff-shop.example.com', $setting->woocommerce_base_url);
    }

    public function test_staff_without_settings_permission_cannot_access_the_page(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['role' => 'sales_staff', 'is_active' => true]);
        $user->companies()->attach($company, ['role' => 'sales_staff', 'is_default' => true]);

        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->getKey(), 'current_company_selection_explicit' => true])
            ->get('/admin/settings/integrations')
            ->assertForbidden();
    }

    public function test_multi_provider_status_cards_reflect_courier_and_ad_manager_records(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeSuperAdmin($company);

        CourierProvider::query()->create([
            'company_id' => $company->getKey(), 'name' => 'Steadfast', 'driver' => 'steadfast',
            'credentials' => ['api_key' => 'x', 'secret_key' => 'y'], 'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->getKey(), 'current_company_selection_explicit' => true]);

        $response = $this->get('/admin/settings/integrations');
        $response->assertOk()
            ->assertSee('Courier Providers')
            ->assertSee('Meta Ads (Ad Manager)');

        MetaAdAccount::query()->create([
            'company_id' => $company->getKey(), 'name' => 'Main Ad Account',
            'credentials' => ['access_token' => 'x'], 'is_active' => true,
        ]);

        $this->get('/admin/settings/integrations')
            ->assertOk()
            ->assertSeeText('Connected');
    }

    public function test_test_webhook_action_warns_when_no_secret_is_saved_yet(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeSuperAdmin($company);
        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->getKey(), 'current_company_selection_explicit' => true]);

        Http::fake();

        Livewire::test(Integrations::class)
            ->call('testWoocommerceWebhook')
            ->assertNotified('No webhook secret saved yet');

        Http::assertNothingSent();
    }

    public function test_test_webhook_action_reports_success_for_a_correctly_signed_round_trip(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeSuperAdmin($company);
        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->getKey(), 'current_company_selection_explicit' => true]);

        StorefrontSetting::query()->create([
            'company_id' => $company->getKey(),
            'woocommerce_credentials' => ['webhook_secret' => 'test-secret-123'],
        ]);

        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        Livewire::test(Integrations::class)
            ->call('testWoocommerceWebhook')
            ->assertNotified('Webhook reachable, signature verified');

        // The exact same request a real WooCommerce delivery would send:
        // uses the just-saved secret, not whatever's unsaved in the form.
        Http::assertSent(function ($request) {
            $expectedSignature = base64_encode(hash_hmac('sha256', '{}', 'test-secret-123', true));

            return $request->header('X-WC-Webhook-Signature') === [$expectedSignature]
                && $request->header('X-WC-Webhook-Topic') === ['order.updated'];
        });
    }

    public function test_test_webhook_action_reports_the_failure_status(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeSuperAdmin($company);
        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->getKey(), 'current_company_selection_explicit' => true]);

        StorefrontSetting::query()->create([
            'company_id' => $company->getKey(),
            'woocommerce_credentials' => ['webhook_secret' => 'test-secret-123'],
        ]);

        Http::fake(['*' => Http::response('Forbidden', 403)]);

        Livewire::test(Integrations::class)
            ->call('testWoocommerceWebhook')
            ->assertNotified('Webhook test failed: HTTP 403');
    }

    public function test_sync_order_action_warns_when_site_url_or_api_keys_are_not_saved(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeSuperAdmin($company);
        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->getKey(), 'current_company_selection_explicit' => true]);

        Http::fake();

        Livewire::test(Integrations::class)
            ->callAction('syncWooOrder', data: ['woo_order_id' => 38044])
            ->assertNotified('WooCommerce site URL or API key/secret is not saved yet');

        Http::assertNothingSent();
    }

    public function test_sync_order_action_pulls_a_real_order_from_woocommerce_and_creates_it_in_the_erp(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeSuperAdmin($company);
        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->getKey(), 'current_company_selection_explicit' => true]);

        StorefrontSetting::query()->create([
            'company_id' => $company->getKey(),
            'woocommerce_base_url' => 'https://shop.example.com',
            'woocommerce_credentials' => ['consumer_key' => 'ck_1', 'consumer_secret' => 'cs_1'],
        ]);

        Http::fake([
            'shop.example.com/wp-json/wc/v3/orders/38044' => Http::response([
                'id' => 38044,
                'number' => '38044',
                'status' => 'processing',
                'date_created' => '2026-08-30T10:00:00',
                'discount_total' => '0.00',
                'total_tax' => '0.00',
                'shipping_total' => '60.00',
                'billing' => [
                    'first_name' => 'Backfilled', 'last_name' => 'Customer',
                    'phone' => '01700000123', 'email' => 'backfilled@example.test',
                    'address_1' => '1 Test Road', 'city' => 'Dhaka',
                ],
                'line_items' => [],
            ], 200),
        ]);

        Livewire::test(Integrations::class)
            ->callAction('syncWooOrder', data: ['woo_order_id' => 38044])
            ->assertNotified('Order synced');

        // Fetched with the saved API credentials, not anything else.
        Http::assertSent(fn ($request): bool => $request->url() === 'https://shop.example.com/wp-json/wc/v3/orders/38044'
            && $request->hasHeader('Authorization'));

        $this->assertDatabaseHas('orders', [
            'company_id' => $company->getKey(),
            'external_reference' => 'woo-38044',
        ]);
    }

    public function test_sync_order_action_reports_the_exact_processing_failure_instead_of_a_generic_message(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeSuperAdmin($company);
        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->getKey(), 'current_company_selection_explicit' => true]);

        StorefrontSetting::query()->create([
            'company_id' => $company->getKey(),
            'woocommerce_base_url' => 'https://shop.example.com',
            'woocommerce_credentials' => ['consumer_key' => 'ck_1', 'consumer_secret' => 'cs_1'],
        ]);

        // No "id" in the response WooCommerce sends back — the same real
        // failure shape WooCommerceOrderSyncService::upsertOrder() rejects
        // with a specific message, used here as a stand-in for any real
        // processing bug: the point is that the exact exception surfaces
        // in the notification instead of a generic "something went wrong".
        Http::fake([
            'shop.example.com/wp-json/wc/v3/orders/38044' => Http::response(['number' => '38044'], 200),
        ]);

        Livewire::test(Integrations::class)
            ->callAction('syncWooOrder', data: ['woo_order_id' => 38044])
            ->assertNotified('Sync failed: WooCommerce order payload is missing an order id.');

        $this->assertDatabaseMissing('orders', ['external_reference' => 'woo-38044']);
    }

    protected function assertBlank(mixed $value): void
    {
        $this->assertTrue(blank($value), 'Expected value to be blank but got: '.var_export($value, true));
    }

    protected function makeCompany(): Company
    {
        $company = Company::query()->create([
            'name' => 'Integrations Co', 'slug' => 'integrations-co-'.uniqid(), 'invoice_prefix' => 'INT'.random_int(100, 999),
            'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);

        return $company;
    }

    protected function makeSuperAdmin(Company $company): User
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $user->companies()->attach($company, ['role' => 'super_admin', 'is_default' => true]);

        return $user;
    }
}
