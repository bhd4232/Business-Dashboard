<?php

namespace Tests\Feature;

use App\Filament\Pages\CompanySettings;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\CompanySettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            ->get('/admin/company-settings')
            ->assertOk()
            ->assertSee('Business Profile')
            ->assertSee('Branding');
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
        app(CompanySettingsService::class)->save([
            'name' => 'ZamZam Trading',
            'address' => 'Dhaka, Bangladesh',
            'phone' => '+8801700000000',
            'email' => 'accounts@example.com',
            'currency' => 'USD',
            'timezone' => 'Asia/Dhaka',
            'date_format' => 'Y-m-d',
        ]);

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
        app(CompanySettingsService::class)->save([
            'name' => 'ZamZam Trading',
            'currency' => 'USD',
            'timezone' => 'Asia/Dhaka',
            'date_format' => 'Y-m-d',
        ]);

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

        $this->actingAs($admin);

        Livewire::test(CompanySettings::class)
            ->set('name', 'Livewire Trading')
            ->set('address', 'Chattogram, Bangladesh')
            ->set('phone', '+8801800000000')
            ->set('email', 'info@example.com')
            ->set('currency', 'BDT')
            ->set('timezone', 'Asia/Dhaka')
            ->set('dateFormat', 'Y-m-d')
            ->call('save')
            ->assertHasNoErrors();

        $profile = app(CompanySettingsService::class)->profile();

        $this->assertSame('Livewire Trading', $profile['name']);
        $this->assertSame('Chattogram, Bangladesh', $profile['address']);
        $this->assertSame('BDT', $profile['currency']);
        $this->assertSame('Y-m-d', $profile['date_format']);
    }

    public function test_company_settings_livewire_save_validates_email(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        Livewire::test(CompanySettings::class)
            ->set('name', 'Invalid Email Company')
            ->set('email', 'not-an-email')
            ->call('save')
            ->assertHasErrors(['email']);
    }

    public function test_company_settings_livewire_uploads_logo(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        Livewire::test(CompanySettings::class)
            ->set('name', 'Logo Company')
            ->set('logoUpload', UploadedFile::fake()->image('logo.png'))
            ->set('darkLogoUpload', UploadedFile::fake()->image('dark-logo.png'))
            ->call('save')
            ->assertHasNoErrors();

        $profile = app(CompanySettingsService::class)->profile();

        $this->assertNotEmpty($profile['logo']);
        $this->assertNotEmpty($profile['dark_logo']);
        $this->assertSame($profile['dark_logo'], AppSetting::getValue('company.dark_logo'));
        Storage::disk('public')->assertExists($profile['logo']);
        Storage::disk('public')->assertExists($profile['dark_logo']);
    }
}
