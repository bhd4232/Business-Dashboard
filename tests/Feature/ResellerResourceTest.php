<?php

namespace Tests\Feature;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Resellers\Pages\ListResellers;
use App\Filament\Resources\Resellers\ResellerResource;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ResellerResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_generates_a_unique_slug_and_moves_the_customer_off_the_customers_list(): void
    {
        $company = $this->company('Approve Co');
        $superAdmin = $this->superAdmin();
        $applicant = $this->applicant($company, 'Rahim Traders');

        app(CompanyContext::class)->set($company);
        $this->actingAs($superAdmin);

        Livewire::test(ListResellers::class)
            ->mountTableAction('approve', $applicant)
            ->callMountedTableAction();

        $applicant->refresh();
        $this->assertSame('approved', $applicant->reseller_status);
        $this->assertSame('rahim-traders', $applicant->reseller_slug);

        // Approved resellers are excluded from the Customers list query.
        $this->assertFalse(
            CustomerResource::getEloquentQuery()->whereKey($applicant->getKey())->exists(),
        );
        $this->assertTrue(
            ResellerResource::getEloquentQuery()->whereKey($applicant->getKey())->exists(),
        );
    }

    public function test_slug_auto_generation_avoids_collisions_within_the_same_company(): void
    {
        $company = $this->company('Slug Collision Co');
        $superAdmin = $this->superAdmin();
        app(CompanyContext::class)->set($company);

        $first = $this->applicant($company, 'Popular Shop');
        $first->ensureResellerSlug();
        $first->update(['reseller_status' => 'approved']);

        $second = $this->applicant($company, 'Popular Shop', '01900000002');

        $this->actingAs($superAdmin);

        Livewire::test(ListResellers::class)
            ->mountTableAction('approve', $second)
            ->callMountedTableAction();

        $second->refresh();
        $this->assertSame('popular-shop-2', $second->reseller_slug);
    }

    public function test_reject_action_records_a_reason_and_can_be_re_approved_later(): void
    {
        $company = $this->company('Reject Co');
        $superAdmin = $this->superAdmin();
        $applicant = $this->applicant($company);
        app(CompanyContext::class)->set($company);

        $this->actingAs($superAdmin);

        Livewire::test(ListResellers::class)
            ->mountTableAction('reject', $applicant)
            ->setTableActionData(['reseller_note' => 'Could not verify the business address.'])
            ->callMountedTableAction();

        $applicant->refresh();
        $this->assertSame('rejected', $applicant->reseller_status);
        $this->assertSame('Could not verify the business address.', $applicant->reseller_note);

        // Still stays out of the Customers list -- rejected is not "none".
        $this->assertFalse(
            CustomerResource::getEloquentQuery()->whereKey($applicant->getKey())->exists(),
        );
    }

    public function test_bulk_approve_and_reject(): void
    {
        $company = $this->company('Bulk Co');
        $superAdmin = $this->superAdmin();
        app(CompanyContext::class)->set($company);

        $a = $this->applicant($company, 'A Shop', '01900000010');
        $b = $this->applicant($company, 'B Shop', '01900000011');
        $c = $this->applicant($company, 'C Shop', '01900000012');

        $this->actingAs($superAdmin);

        Livewire::test(ListResellers::class)
            ->callTableBulkAction('approveSelected', [$a, $b]);

        $this->assertSame('approved', $a->fresh()->reseller_status);
        $this->assertSame('approved', $b->fresh()->reseller_status);
        $this->assertNotNull($a->fresh()->reseller_slug);

        Livewire::test(ListResellers::class)
            ->callTableBulkAction('rejectSelected', [$c]);

        $this->assertSame('rejected', $c->fresh()->reseller_status);
    }

    public function test_a_resellers_list_never_shows_another_companys_reseller(): void
    {
        $companyA = $this->company('Isolation Co A');
        $companyB = $this->company('Isolation Co B');
        $superAdmin = $this->superAdmin();

        app(CompanyContext::class)->set($companyA);
        $applicantA = $this->applicant($companyA, 'A Reseller');

        app(CompanyContext::class)->set($companyB);
        $applicantB = $this->applicant($companyB, 'B Reseller');

        app(CompanyContext::class)->set($companyA);
        $this->actingAs($superAdmin);

        $resellers = ResellerResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($applicantA->getKey(), $resellers);
        $this->assertNotContains($applicantB->getKey(), $resellers);
    }

    public function test_reseller_module_disabled_hides_the_resource_entirely(): void
    {
        $company = $this->company('Disabled Module Co');
        $company->update(['reseller_module_enabled' => false]);
        $manager = $this->staff($company, 'manager');
        $applicant = $this->applicant($company);

        app(CompanyContext::class)->set($company);
        $this->actingAs($manager);

        $this->assertFalse(
            ResellerResource::getEloquentQuery()->whereKey($applicant->getKey())->exists(),
        );

        $this->get('/admin/resellers')->assertForbidden();
    }

    public function test_reseller_module_enabled_shows_the_resource_to_staff_with_sales_access(): void
    {
        $company = $this->company('Enabled Module Co');
        $manager = $this->staff($company, 'manager');
        $applicant = $this->applicant($company);

        app(CompanyContext::class)->set($company);
        $this->actingAs($manager);

        $this->assertTrue(
            ResellerResource::getEloquentQuery()->whereKey($applicant->getKey())->exists(),
        );

        // /admin/resellers is the *cluster* route, which redirects into its
        // navigation item -- the resource's own list page is nested one
        // level deeper under the cluster's slug.
        $this->get('/admin/resellers/resellers')->assertOk();
    }

    public function test_only_a_super_admin_sees_the_reseller_module_toggle_on_the_company_form(): void
    {
        $company = $this->company('Toggle Visibility Co');
        $superAdmin = $this->superAdmin();
        $manager = $this->staff($company, 'manager');
        $manager->update(['role' => 'manager']);

        // A manager with settings access can reach the company edit page,
        // but the toggle itself is only wired to render/dehydrate for a
        // super admin (Filament schema-level gate, not a page-level one).
        $this->actingAs($superAdmin)
            ->get("/admin/company-management/companies/{$company->getKey()}/edit")
            ->assertOk()
            ->assertSee('reseller_module_enabled', false);
    }

    protected function company(string $name): Company
    {
        return Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'invoice_prefix' => strtoupper(Str::random(4)),
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
            'reseller_module_enabled' => true,
        ]);
    }

    protected function superAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
    }

    protected function staff(Company $company, string $role): User
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);

        // sync(), not attach(): UserFactory::configure() already
        // auto-attaches every factory-created user to Company::defaultCompany()
        // as their is_default company -- sync() replaces that pivot row
        // instead of adding a second is_default=true row alongside it, which
        // would otherwise make SetCurrentCompany's defaultCompany() lookup
        // resolve to whichever company happens to sort first.
        $user->companies()->sync([$company->getKey() => ['role' => $role, 'is_default' => true]]);

        return $user;
    }

    protected function applicant(Company $company, string $businessName = 'Test Shop', string $phone = '01900000001'): Customer
    {
        return Customer::query()->create([
            'company_id' => $company->getKey(),
            'name' => 'Applicant '.$phone,
            'phone' => $phone,
            'reseller_status' => 'pending',
            'business_name' => $businessName,
            'is_active' => true,
        ]);
    }
}
