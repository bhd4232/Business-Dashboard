<?php

namespace Tests\Feature;

use App\Filament\Pages\CompanySettings;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Companies\Pages\CreateCompany;
use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Models\AppSetting;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\CompanyContext;
use App\Services\CompanySettingsService;
use App\Services\CompanyStorageService;
use App\Services\StorageSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_settings_service_saves_profile(): void
    {
        $settings = app(CompanySettingsService::class);

        $settings->save([
            'name' => 'ZamZam Trading',
            'address' => 'Dhaka, Bangladesh',
            'phone' => '+8801700000000',
            'email' => 'accounts@example.com',
            'currency' => 'USD',
            'timezone' => 'Asia/Dhaka',
            'date_format' => 'Y-m-d',
        ]);

        $profile = $settings->profile();

        $this->assertSame('ZamZam Trading', $profile['name']);
        $this->assertSame('Dhaka, Bangladesh', $profile['address']);
        $this->assertSame('USD', $profile['currency']);
        $this->assertSame('Asia/Dhaka', $profile['timezone']);
        $this->assertSame('Y-m-d', $profile['date_format']);
    }

    public function test_company_settings_page_requires_settings_permission(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $salesStaff = User::factory()->create([
            'role' => 'sales_staff',
            'is_active' => true,
        ]);

        $this->actingAs($salesStaff)
            ->get('/admin/company-settings')
            ->assertForbidden();

        $this->actingAs($admin)
            ->withSession(['current_company_id' => $admin->defaultCompany()->getKey()])
            ->get('/admin/company-settings')
            ->assertOk()
            ->assertSee('Business Profile')
            ->assertSee('Branding')
            ->assertSee('Invoice Settings');

        $this->actingAs($admin)
            ->withSession(['current_company_id' => 'all'])
            ->get('/admin/company-settings')
            ->assertNotFound();
    }

    public function test_admin_panel_uses_company_name_as_brand(): void
    {
        app(CompanySettingsService::class)->save([
            'name' => 'ZamZam ERP',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'date_format' => 'd M Y',
        ]);

        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('ZamZam ERP')
            ->assertDontSee('>Laravel<', false);
    }

    public function test_admin_panel_uses_light_and_dark_company_logos(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('company/light-logo.png', 'light-logo');
        Storage::disk('public')->put('company/dark-logo.png', 'dark-logo');

        app(CompanySettingsService::class)->save([
            'name' => 'ZamZam ERP',
            'logo' => 'company/light-logo.png',
            'dark_logo' => 'company/dark-logo.png',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'date_format' => 'd M Y',
        ]);

        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('/storage/company/light-logo.png')
            ->assertSee('/storage/company/dark-logo.png');
    }

    public function test_invoice_print_uses_company_settings(): void
    {
        $company = Company::defaultCompany();
        app(CompanySettingsService::class)->save([
            'name' => 'ZamZam Trading',
            'address' => 'Dhaka, Bangladesh',
            'phone' => '+8801700000000',
            'email' => 'accounts@example.com',
            'currency' => 'USD',
            'timezone' => 'Asia/Dhaka',
            'date_format' => 'Y-m-d',
        ], $company);

        $user = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $customer = Customer::query()->create(['name' => 'Invoice Customer']);
        $product = Product::query()->create([
            'name' => 'Invoice Product',
            'sku' => 'INV-BRAND-001',
            'price' => 100,
            'sale_price' => 100,
            'stock' => 10,
        ]);
        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'order_date' => '2026-06-14',
            'status' => 'draft',
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
        ]);

        $this->actingAs($user)
            ->get(route('orders.print', $order))
            ->assertOk()
            ->assertSee('ZamZam Trading')
            ->assertSee('Dhaka, Bangladesh')
            ->assertSee('Unit Price (USD)')
            ->assertSee('100.00')
            ->assertDontSee('Discount')
            ->assertDontSee('VAT')
            ->assertDontSee('>Paid<', false)
            ->assertSee('invoice-print-button')
            ->assertSee('window.print()');
    }

    public function test_invoice_print_hides_zero_adjustments_and_shows_paid_as_negative_when_present(): void
    {
        $company = Company::defaultCompany();
        app(CompanySettingsService::class)->save([
            'name' => 'ZamZam Trading',
            'currency' => 'USD',
            'timezone' => 'Asia/Dhaka',
            'date_format' => 'Y-m-d',
        ], $company);

        $user = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $customer = Customer::query()->create(['name' => 'Invoice Customer']);
        $product = Product::query()->create([
            'name' => 'Invoice Product',
            'sku' => 'INV-PAID-001',
            'price' => 100,
            'sale_price' => 100,
            'stock' => 10,
        ]);
        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'order_date' => '2026-06-14',
            'status' => 'draft',
            'discount' => 10,
            'vat' => 5,
            'paid_amount' => 20,
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
        ]);

        $this->actingAs($user)
            ->get(route('orders.print', $order))
            ->assertOk()
            ->assertSee('Discount')
            ->assertSee('VAT')
            ->assertSee('<td class="t-label">Paid</td>', false)
            ->assertSee('-20.00')
            ->assertSee('75.00');
    }

    public function test_company_settings_livewire_save_updates_profile(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $company = $admin->defaultCompany();
        app(CompanyContext::class)->set($company);

        $this->actingAs($admin);

        Livewire::test(CompanySettings::class)
            ->fillForm([
                'name' => 'Livewire Trading',
                'address' => 'Chattogram, Bangladesh',
                'phone' => '+8801800000000',
                'email' => 'info@example.com',
                'currency' => 'BDT',
                'timezone' => 'Asia/Dhaka',
                'date_format' => 'Y-m-d',
                'invoice_prefix' => 'LWT',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $profile = app(CompanySettingsService::class)->profile($company->fresh());

        $this->assertSame('Livewire Trading', $profile['name']);
        $this->assertSame('Chattogram, Bangladesh', $profile['address']);
        $this->assertSame('BDT', $profile['currency']);
        $this->assertSame('Y-m-d', $profile['date_format']);
        $this->assertSame('LWT', $profile['invoice_prefix']);
    }

    public function test_company_settings_page_saves_nested_invoice_values_only_for_the_mounted_company(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $first = $admin->defaultCompany();
        $second = Company::query()->create([
            'name' => 'Second Invoice Company',
            'slug' => 'second-invoice-company',
            'invoice_prefix' => 'SIC',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        $settings = app(CompanySettingsService::class);
        $settings->saveInvoice(['hotline' => '01800000000'], $second);
        app(CompanyContext::class)->set($first);
        $this->actingAs($admin);

        Livewire::test(CompanySettings::class)
            ->assertSet('companyId', $first->getKey())
            ->set('data.invoice_prefix', 'FIRST')
            ->set('data.invoice.hotline', '01700000000')
            ->set('data.invoice.thank_you', 'First company thanks')
            ->set('data.invoice.show_weight', false)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('FIRST', $first->fresh()->invoice_prefix);
        $this->assertSame('01700000000', $settings->invoice($first->fresh())['hotline']);
        $this->assertSame('First company thanks', $settings->invoice($first->fresh())['thank_you']);
        $this->assertFalse($settings->invoice($first->fresh())['show_weight']);
        $this->assertSame('SIC', $second->fresh()->invoice_prefix);
        $this->assertSame('01800000000', $settings->invoice($second->fresh())['hotline']);

        app(CompanyContext::class)->set($second->fresh());

        Livewire::test(CompanySettings::class)
            ->set('data.invoice_prefix', 'FIRST')
            ->set('data.invoice.hotline', '01900000000')
            ->call('save')
            ->assertHasFormErrors(['invoice_prefix' => 'unique']);

        $this->assertSame('SIC', $second->fresh()->invoice_prefix);
        $this->assertSame('01800000000', $settings->invoice($second->fresh())['hotline']);
    }

    public function test_company_settings_rejects_a_company_context_change_after_mount(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $first = $admin->defaultCompany();
        $second = Company::query()->create([
            'name' => 'Context Switch Company',
            'slug' => 'context-switch-company',
            'invoice_prefix' => 'CSC',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        app(CompanyContext::class)->set($first);
        $this->actingAs($admin);

        $page = Livewire::test(CompanySettings::class)
            ->assertSet('companyId', $first->getKey())
            ->set('data.name', 'Stale Company Name');

        app(CompanyContext::class)->set($second);

        $page->call('save')->assertStatus(409);

        $this->assertNotSame('Stale Company Name', $first->fresh()->name);
        $this->assertSame('Context Switch Company', $second->fresh()->name);
    }

    public function test_company_settings_service_rejects_a_duplicate_invoice_prefix(): void
    {
        $first = Company::defaultCompany();
        $second = Company::query()->create([
            'name' => 'Unique Prefix Company',
            'slug' => 'unique-prefix-company',
            'invoice_prefix' => 'UPC',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(CompanySettingsService::class)->save([
            'name' => $second->name,
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'invoice_prefix' => $first->invoice_prefix,
        ], $second);
    }

    public function test_company_creation_defers_logo_upload_until_the_company_storage_root_exists(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        app(CompanyContext::class)->set($admin->defaultCompany());
        $this->actingAs($admin);

        Livewire::test(CreateCompany::class)
            ->assertFormFieldDisabled('logo');
    }

    public function test_superadmin_can_edit_and_reactivate_an_inactive_company(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $company = Company::query()->create([
            'name' => 'Inactive Company',
            'slug' => 'inactive-company',
            'invoice_prefix' => 'INA',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => false,
        ]);
        $this->actingAs($admin);

        $this->assertTrue(CompanyResource::canView($company));
        $this->assertTrue(CompanyResource::canEdit($company));

        Livewire::test(EditCompany::class, ['record' => $company->getKey()])
            ->set('data.is_active', true)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($company->fresh()->is_active);
    }

    public function test_company_settings_livewire_save_validates_email(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        app(CompanyContext::class)->set($admin->defaultCompany());

        $this->actingAs($admin);

        Livewire::test(CompanySettings::class)
            ->fillForm([
                'name' => 'Invalid Email Company',
                'email' => 'not-an-email',
            ])
            ->call('save')
            ->assertHasFormErrors(['email']);
    }

    public function test_company_settings_livewire_uploads_logo(): void
    {
        Storage::fake('public');
        $storageSettings = app(StorageSettingsService::class);
        $r2Settings = [
            'enabled' => false,
            'access_key_id' => 'test-access-key',
            'secret_access_key' => 'test-secret-key',
            'public_bucket' => 'test-public-bucket',
            'endpoint' => 'https://example.r2.cloudflarestorage.com',
            'public_url' => 'https://cdn.example.test',
        ];
        $storageSettings->save($r2Settings);
        AppSetting::setValue(StorageSettingsService::PUBLIC_TOPOLOGY_LOCKED, '1');
        $storageSettings->forgetCachedSettings();
        $storageSettings->save([...$r2Settings, 'enabled' => true]);
        Storage::fake('r2_public');

        $company = Company::query()->create([
            'name' => 'Logo Company',
            'slug' => 'logo-company',
            'invoice_prefix' => 'LOG',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);

        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        Livewire::test(CompanySettings::class)
            ->fillForm([
                'name' => 'Logo Company',
                'logo' => UploadedFile::fake()->image('logo.png'),
                'dark_logo' => UploadedFile::fake()->image('dark-logo.png'),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $profile = app(CompanySettingsService::class)->profile($company->fresh());

        $this->assertNotEmpty($profile['logo']);
        $this->assertNotEmpty($profile['dark_logo']);
        $this->assertNotSame($profile['dark_logo'], AppSetting::getValue('company.dark_logo'));
        $this->assertStringStartsWith($company->storageRoot().'/public/company/', $profile['logo']);
        $this->assertStringStartsWith($company->storageRoot().'/public/company/', $profile['dark_logo']);

        $disk = app(CompanyStorageService::class)->publicDiskName();
        $this->assertSame('r2_public', $disk);
        Storage::disk($disk)->assertExists($profile['logo']);
        Storage::disk($disk)->assertExists($profile['dark_logo']);
        Storage::disk('public')->assertMissing($profile['logo']);
        Storage::disk('public')->assertMissing($profile['dark_logo']);
    }

    public function test_remote_company_logo_path_is_a_bounded_pdf_safe_data_uri(): void
    {
        $storageSettings = app(StorageSettingsService::class);
        $r2Settings = [
            'enabled' => false,
            'access_key_id' => 'test-access-key',
            'secret_access_key' => 'test-secret-key',
            'public_bucket' => 'test-public-bucket',
            'endpoint' => 'https://example.r2.cloudflarestorage.com',
            'public_url' => 'https://cdn.example.test',
        ];
        $storageSettings->save($r2Settings);
        AppSetting::setValue(StorageSettingsService::PUBLIC_TOPOLOGY_LOCKED, '1');
        $storageSettings->forgetCachedSettings();
        $storageSettings->save([...$r2Settings, 'enabled' => true]);
        Storage::fake('r2_public');

        $company = Company::query()->create([
            'name' => 'Remote Logo Company',
            'slug' => 'remote-logo-company',
            'invoice_prefix' => 'RLC',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        $storage = app(CompanyStorageService::class);
        $logo = $storage->putPublic($company, 'company', 'logo.png', 'remote-logo-bytes');
        $company->forceFill(['logo' => $logo])->save();

        $this->assertSame(
            'data:image/png;base64,'.base64_encode('remote-logo-bytes'),
            app(CompanySettingsService::class)->logoPath($company),
        );
    }
}
