<?php

namespace Tests\Feature;

use App\Filament\Resources\StorefrontSettings\Pages\CreateStorefrontSetting;
use App\Filament\Resources\StorefrontSettings\Pages\EditStorefrontSetting;
use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\StorefrontPage;
use App\Models\StorefrontSetting;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PhaseFourAdminPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_four_admin_pages_render_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $account = Account::query()->create(['name' => 'Admin Cash', 'opening_balance' => 1000]);
        $customer = Customer::query()->create(['name' => 'Admin Customer', 'opening_balance' => 200]);
        $supplier = Supplier::query()->create(['name' => 'Admin Supplier', 'opening_balance' => 200]);
        $category = ExpenseCategory::query()->create(['name' => 'Admin Expense', 'slug' => 'admin-expense']);
        $company = $user->defaultCompany();
        $company->forceFill([
            'domain' => 'admin-store.example.test',
            'domain_verified' => true,
        ])->save();

        app(CompanyContext::class)->set($company);

        $storefrontSetting = StorefrontSetting::query()->updateOrCreate(
            ['company_id' => $company->getKey()],
            [
                'is_published' => true,
                'theme_color' => '#0F766E',
                'meta_title' => 'Admin Storefront',
                'meta_description' => 'Admin managed storefront.',
                'whatsapp_number' => '+8801700000000',
            ],
        );
        Product::query()->create([
            'name' => 'Admin Storefront Product',
            'sku' => 'ADMIN-STOREFRONT-001',
            'price' => 1000,
            'sale_price' => 900,
            'cost_price' => 500,
            'stock' => 5,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);
        $storefrontPage = StorefrontPage::query()->create([
            'company_id' => $company->getKey(),
            'title' => 'Admin Policy',
            'slug' => 'admin-policy',
            'content' => 'Admin managed policy page.',
            'is_published' => true,
        ]);

        $customerPayment = CustomerPayment::query()->create([
            'customer_id' => $customer->id,
            'account_id' => $account->id,
            'amount' => 100,
            'payment_date' => now(),
        ]);
        $supplierPayment = SupplierPayment::query()->create([
            'supplier_id' => $supplier->id,
            'account_id' => $account->id,
            'amount' => 50,
            'payment_date' => now(),
        ]);
        $expense = Expense::query()->create([
            'expense_category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 25,
            'expense_date' => now(),
        ]);

        foreach ([
            '/admin/accounts/accounts',
            '/admin/sales/customer-payments',
            '/admin/purchasing/supplier-payments',
            '/admin/accounts/expense-categories',
            '/admin/accounts/expenses',
            '/admin/accounts/transaction-ledgers',
            '/admin/storefront/storefront-settings',
            '/admin/storefront/storefront-pages',
            "/admin/sales/customer-payments/{$customerPayment->id}",
            "/admin/purchasing/supplier-payments/{$supplierPayment->id}",
            "/admin/accounts/expenses/{$expense->id}",
            "/admin/storefront/storefront-settings/{$storefrontSetting->id}/edit",
            "/admin/storefront/storefront-pages/{$storefrontPage->id}/edit",
        ] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }

        $this->actingAs($user)
            ->get('/admin/storefront/storefront-settings')
            ->assertOk()
            ->assertSee('Launch Readiness')
            ->assertSee('Missing Setup')
            ->assertSee('Products')
            ->assertSee('Pages')
            ->assertSee('Preview')
            ->assertSee('Open Site');

        $this->actingAs($user)
            ->get("/admin/storefront/storefront-settings/{$storefrontSetting->id}/edit")
            ->assertOk()
            ->assertSee('Domain and Launch Readiness')
            ->assertSee('Storefront Domain')
            ->assertSee('Domain verified')
            ->assertSee('Launch Readiness')
            ->assertSee('Missing Setup')
            ->assertSee('Visible Products')
            ->assertSee('Published Pages');
    }

    public function test_storefront_settings_edit_synchronizes_company_domain_fields(): void
    {
        $user = User::factory()->create();
        $company = $user->defaultCompany();
        $company->forceFill([
            'domain' => 'old-store.example.test',
            'domain_verified' => true,
        ])->save();
        app(CompanyContext::class)->set($company);

        $setting = StorefrontSetting::query()->create([
            'company_id' => $company->getKey(),
            'is_published' => true,
            'theme_color' => '#0F766E',
        ]);

        $this->actingAs($user);

        $component = Livewire::test(EditStorefrontSetting::class, ['record' => $setting->getKey()])
            ->assertFormFieldExists('company_domain')
            ->assertFormFieldExists('company_domain_verified')
            ->assertSet('data.company_domain', 'old-store.example.test')
            ->assertSet('data.company_domain_verified', true);

        $headerActions = collect($component->instance()->getCachedHeaderActions());

        $this->assertSame(
            ['syncWooCommerce', 'managePages', 'createPage', 'saveChanges'],
            $headerActions->map->getName()->all(),
        );
        $this->assertSame('Save changes', $headerActions->last()->getLabel());
        $this->assertSame(['mod+s'], $headerActions->last()->getKeyBindings());
        $this->assertSame('save', $headerActions->last()->getLivewireClickHandler());

        $component
            ->set('data.company_id', $company->getKey())
            ->set('data.company_domain', 'https://www.synced-store.example.test/catalog')
            ->set('data.company_domain_verified', true)
            ->set('data.is_published', true)
            ->set('data.theme_color', '#0F766E')
            ->set('data.whatsapp_number', '+8801700000000')
            ->set('data.meta_title', 'Synced Store')
            ->set('data.meta_description', 'Synced storefront settings.')
            ->call('save')
            ->assertHasNoFormErrors();

        $company->refresh();

        $this->assertSame('synced-store.example.test', $company->domain);
        $this->assertFalse($company->domain_verified);

        Livewire::test(EditStorefrontSetting::class, ['record' => $setting->getKey()])
            ->assertSet('data.company_domain', 'synced-store.example.test')
            ->assertSet('data.company_domain_verified', false)
            ->set('data.company_domain', 'https://www.synced-store.example.test')
            ->set('data.company_domain_verified', true)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($company->refresh()->domain_verified);
    }

    public function test_storefront_settings_create_keeps_domain_controls_and_saves_from_the_header(): void
    {
        $user = User::factory()->create();
        $company = $user->defaultCompany();
        $company->forceFill([
            'domain' => 'create-store.example.test',
            'domain_verified' => false,
        ])->save();
        app(CompanyContext::class)->set($company);
        $this->actingAs($user);

        $component = Livewire::test(CreateStorefrontSetting::class)
            ->assertFormFieldExists('company_domain')
            ->assertFormFieldExists('company_domain_verified');

        $headerActions = collect($component->instance()->getCachedHeaderActions());

        $this->assertSame(['saveChanges'], $headerActions->map->getName()->all());
        $this->assertSame('Save changes', $headerActions->first()->getLabel());
        $this->assertSame(['mod+s'], $headerActions->first()->getKeyBindings());
        $this->assertSame('create', $headerActions->first()->getLivewireClickHandler());

        $component
            ->fillForm([
                'company_id' => $company->getKey(),
                'company_domain' => 'https://www.create-store.example.test',
                'company_domain_verified' => true,
                'is_published' => false,
                'customer_accounts_enabled' => true,
                'theme_color' => '#0F766E',
                'theme_mode' => 'system',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('storefront_settings', [
            'company_id' => $company->getKey(),
            'is_published' => false,
        ]);
        $this->assertSame('create-store.example.test', $company->refresh()->domain);
        $this->assertTrue($company->domain_verified);
    }

    public function test_storefront_settings_rejects_duplicate_company_domain_before_database_error(): void
    {
        $user = User::factory()->create();
        $company = $user->defaultCompany();
        $otherCompany = Company::query()->create([
            'name' => 'Other Domain Company',
            'slug' => 'other-domain-company',
            'domain' => 'zamzamgadgetbd.com',
            'invoice_prefix' => 'ODC',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        app(CompanyContext::class)->set($company);

        $setting = StorefrontSetting::query()->create([
            'company_id' => $company->getKey(),
            'is_published' => true,
            'theme_color' => '#0F766E',
        ]);

        $this->actingAs($user);

        Livewire::test(EditStorefrontSetting::class, ['record' => $setting->getKey()])
            ->set('data.company_id', $company->getKey())
            ->set('data.company_domain', 'https://www.zamzamgadgetbd.com')
            ->set('data.company_domain_verified', true)
            ->set('data.is_published', true)
            ->set('data.theme_color', '#0F766E')
            ->call('save')
            ->assertHasFormErrors(['company_domain']);

        $this->assertNull($company->refresh()->domain);
        $this->assertSame('zamzamgadgetbd.com', $otherCompany->refresh()->domain);
    }
}
