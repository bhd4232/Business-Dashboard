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
            ->set('data.ai_provider', 'anthropic')
            ->set('data.ai_model', 'claude-test')
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
        $this->assertSame('claude-test', $ai['model']);
        $this->assertSame('sk-live-test', $ai['api_key']);

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
            ->set('data.ai_provider', 'openai')
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
