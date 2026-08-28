<?php

namespace Tests\Feature;

use App\Filament\Resources\StorefrontPaymentMethods\Pages\CreateStorefrontPaymentMethod;
use App\Filament\Resources\StorefrontPaymentMethods\StorefrontPaymentMethodResource;
use App\Models\Company;
use App\Models\StorefrontPaymentMethod;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StorefrontPaymentMethodResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_a_manual_payment_method_for_the_current_company(): void
    {
        $company = $this->company();
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(CreateStorefrontPaymentMethod::class)
            ->fillForm([
                'type' => StorefrontPaymentMethod::TYPE_MANUAL,
                'name' => 'Rocket (Send Money)',
                'account_number' => '01700000000',
                'instructions' => 'Send Money then enter the Transaction ID.',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // StorefrontSetting::created() already seeded an active `cod` row
        // for this company alongside the manual one just created above.
        $method = StorefrontPaymentMethod::query()->where('type', StorefrontPaymentMethod::TYPE_MANUAL)->sole();
        $this->assertSame($company->getKey(), $method->company_id);
        $this->assertSame('Rocket (Send Money)', $method->name);
        $this->assertSame('manual:'.$method->getKey(), $method->paymentValue());
    }

    public function test_a_company_cannot_have_two_cash_on_delivery_rows(): void
    {
        $company = $this->company();
        $admin = $this->admin();

        // StorefrontSetting::created() already seeded one active `cod` row
        // for this company (see app/Models/StorefrontSetting.php) - creating
        // a second one must be rejected by the form's own type-uniqueness
        // rule, not just collide silently.
        Livewire::actingAs($admin)
            ->test(CreateStorefrontPaymentMethod::class)
            ->fillForm([
                'type' => StorefrontPaymentMethod::TYPE_COD,
                'name' => 'Cash on Delivery (duplicate)',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['type']);

        $this->assertSame(1, StorefrontPaymentMethod::query()->where('type', StorefrontPaymentMethod::TYPE_COD)->count());
    }

    public function test_the_same_name_is_rejected_within_one_company_but_allowed_across_different_companies(): void
    {
        $companyA = $this->company('company-a', 'CO-A');
        $admin = $this->admin();

        StorefrontPaymentMethod::query()->create([
            'company_id' => $companyA->getKey(),
            'type' => StorefrontPaymentMethod::TYPE_MANUAL,
            'name' => 'Bank Transfer',
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(CreateStorefrontPaymentMethod::class)
            ->fillForm([
                'type' => StorefrontPaymentMethod::TYPE_MANUAL,
                'name' => 'Bank Transfer',
                'account_number' => '1234567890',
            ])
            ->call('create')
            ->assertHasFormErrors(['name']);

        // Switching context to a second company: the exact same name must
        // be allowed there, proving the uniqueness check is company-scoped
        // (CompanyScopedUnique), not global.
        $companyB = Company::query()->create([
            'name' => 'Company B', 'slug' => 'company-b', 'invoice_prefix' => 'CO-B',
            'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        app(CompanyContext::class)->set($companyB);

        Livewire::actingAs($admin)
            ->test(CreateStorefrontPaymentMethod::class)
            ->fillForm([
                'type' => StorefrontPaymentMethod::TYPE_MANUAL,
                'name' => 'Bank Transfer',
                'account_number' => '0987654321',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, StorefrontPaymentMethod::withoutGlobalScopes()->where('name', 'Bank Transfer')->count());
    }

    public function test_staff_without_settings_permission_cannot_access_the_resource(): void
    {
        $this->company();
        $staff = User::factory()->create(['role' => 'sales_staff', 'is_active' => true]);

        $this->assertFalse($staff->canManageSettings());
        $this->actingAs($staff);
        $this->assertFalse(StorefrontPaymentMethodResource::canViewAny());
    }

    private function company(string $slug = 'payment-methods-co', string $prefix = 'PMC'): Company
    {
        $company = Company::query()->create([
            'name' => 'Store '.$slug,
            'slug' => $slug,
            'domain' => $slug.'.example.test',
            'domain_verified' => true,
            'invoice_prefix' => $prefix,
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        \App\Models\StorefrontSetting::query()->create([
            'company_id' => $company->getKey(),
            'theme_color' => '#0F766E',
            'is_published' => true,
        ]);

        app(CompanyContext::class)->set($company);

        return $company;
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
    }
}
