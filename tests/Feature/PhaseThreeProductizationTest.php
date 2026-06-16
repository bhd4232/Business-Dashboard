<?php

namespace Tests\Feature;

use App\Filament\Pages\ProductSetup;
use App\Models\AppSetting;
use App\Models\User;
use App\Services\LicenseActivationService;
use App\Services\ProductSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PhaseThreeProductizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_product_pages_render(): void
    {
        $this->get('/')->assertOk()->assertSee('ZamZam ERP');
        $this->get('/pricing')->assertOk()->assertSee('Pricing');
        $this->get('/docs')->assertOk()->assertSee('Documentation');
    }

    public function test_installer_creates_first_admin_and_settings(): void
    {
        $csrfToken = 'test-token';

        $this->withSession(['_token' => $csrfToken])->post('/install', [
            '_token' => $csrfToken,
            'company_name' => 'Install Ready ERP',
            'company_email' => 'owner@example.com',
            'company_phone' => '+8801700000000',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'date_format' => 'd M Y',
            'admin_name' => 'Owner Admin',
            'admin_email' => 'owner@example.com',
            'admin_password' => 'Password123!',
            'admin_password_confirmation' => 'Password123!',
            'license_key' => 'ZZERP-AB12-CD34-EF56',
            'demo_mode' => '1',
        ])->assertRedirect('/admin/login');

        $this->assertDatabaseHas('users', [
            'email' => 'owner@example.com',
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $this->assertSame('Install Ready ERP', AppSetting::getValue('company.name'));
        $this->assertTrue(app(ProductSetupService::class)->onboardingCompleted());
        $this->assertTrue(app(ProductSetupService::class)->demoMode());
        $this->assertTrue(app(LicenseActivationService::class)->isActive());
    }

    public function test_installer_redirects_when_admin_exists(): void
    {
        User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->get('/install')->assertRedirect('/admin/login');
    }

    public function test_product_setup_page_saves_onboarding_demo_and_license(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        Livewire::test(ProductSetup::class)
            ->set('companyName', 'Setup Company')
            ->set('companyEmail', 'setup@example.com')
            ->set('currency', 'USD')
            ->set('timezone', 'Asia/Dhaka')
            ->set('dateFormat', 'Y-m-d')
            ->set('onboardingCompleted', true)
            ->set('demoMode', true)
            ->set('demoNotice', 'Demo mode is on.')
            ->call('saveSetup')
            ->assertHasNoErrors()
            ->set('licenseKey', 'ZZERP-1234-ABCD-5678')
            ->set('licensedTo', 'Setup Company')
            ->set('supportEmail', 'support@example.com')
            ->call('activateLicense')
            ->assertHasNoErrors();

        $this->assertSame('Setup Company', AppSetting::getValue('company.name'));
        $this->assertSame('USD', AppSetting::getValue('company.currency'));
        $this->assertTrue(app(ProductSetupService::class)->onboardingCompleted());
        $this->assertTrue(app(ProductSetupService::class)->demoMode());
        $this->assertTrue(app(LicenseActivationService::class)->isActive());
    }

    public function test_demo_mode_blocks_write_actions_for_non_super_admins(): void
    {
        app(ProductSetupService::class)->save([
            'installed' => true,
            'onboarding_completed' => true,
            'demo_mode' => true,
            'demo_notice' => 'Demo mode is on.',
        ]);

        $staff = User::factory()->create([
            'role' => 'sales_staff',
            'is_active' => true,
        ]);
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $staffCsrfToken = 'staff-test-token';

        $this->actingAs($staff)
            ->withSession(['_token' => $staffCsrfToken])
            ->post('/install', ['_token' => $staffCsrfToken])
            ->assertStatus(423);

        $adminCsrfToken = 'admin-test-token';

        $this->actingAs($admin)
            ->withSession(['_token' => $adminCsrfToken])
            ->post('/install', ['_token' => $adminCsrfToken])
            ->assertRedirect('/admin/login');
    }
}
