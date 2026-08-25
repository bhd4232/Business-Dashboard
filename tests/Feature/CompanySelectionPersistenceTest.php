<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CompanySelectionPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_default_company_is_selected_when_session_is_empty(): void
    {
        [$user, , $defaultCompany] = $this->superAdminWithTwoCompanies();

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertSessionHas('current_company_id', $defaultCompany->getKey());

        $this->assertSame($defaultCompany->getKey(), app(CompanyContext::class)->id());
        $this->assertFalse(app(CompanyContext::class)->isAllCompanies());
    }

    public function test_explicit_all_companies_selection_is_preserved(): void
    {
        [$user, , $defaultCompany] = $this->superAdminWithTwoCompanies();

        $this->actingAs($user)
            ->withSession(['current_company_id' => $defaultCompany->getKey()])
            ->post(route('admin.company.switch'), ['company_id' => 'all'])
            ->assertRedirect()
            ->assertSessionHas('current_company_id', 'all')
            ->assertSessionHas('current_company_selection_explicit', true);

        $this
            ->get('/admin')
            ->assertOk()
            ->assertSessionHas('current_company_id', 'all')
            ->assertSessionHas('current_company_selection_explicit', true);

        $this->assertTrue(app(CompanyContext::class)->isAllCompanies());
    }

    public function test_saving_own_default_company_updates_the_active_session(): void
    {
        [$user, $firstCompany, $defaultCompany] = $this->superAdminWithTwoCompanies();

        $this->actingAs($user)->withSession([
            'current_company_id' => 'all',
            'current_company_selection_explicit' => true,
        ]);
        app(CompanyContext::class)->all();

        Livewire::test(EditUser::class, ['record' => $user->getKey()])
            ->set('data.company_ids', [$firstCompany->getKey(), $defaultCompany->getKey()])
            ->set('data.default_company_id', $defaultCompany->getKey())
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertSessionHas('current_company_id', $defaultCompany->getKey());

        $this->assertFalse((bool) session('current_company_selection_explicit'));

        $this->assertSame($defaultCompany->getKey(), $user->fresh()->defaultCompany()?->getKey());
        $this->assertSame($defaultCompany->getKey(), app(CompanyContext::class)->id());

        $this->get('/admin')
            ->assertOk()
            ->assertSessionHas('current_company_id', $defaultCompany->getKey());
    }

    public function test_switching_to_a_specific_non_default_company_actually_loads_that_company(): void
    {
        [$user, $firstCompany, $defaultCompany] = $this->superAdminWithTwoCompanies();

        // Start on the default company, same as a fresh /admin visit would.
        $this->actingAs($user)
            ->withSession(['current_company_id' => $defaultCompany->getKey()])
            ->get('/admin')
            ->assertSessionHas('current_company_id', $defaultCompany->getKey());

        // Switch to the OTHER (non-default) company from the header.
        $this
            ->post(route('admin.company.switch'), ['company_id' => $firstCompany->getKey()])
            ->assertRedirect()
            ->assertSessionHas('current_company_id', $firstCompany->getKey())
            ->assertSessionHas('current_company_selection_explicit', true);

        // The very next page load must reflect the newly switched company, not the default.
        $this
            ->get('/admin')
            ->assertOk()
            ->assertSessionHas('current_company_id', $firstCompany->getKey());

        $this->assertSame($firstCompany->getKey(), app(CompanyContext::class)->id());
    }

    public function test_non_super_admin_staff_switching_to_a_specific_company_actually_loads_that_company(): void
    {
        $firstCompany = Company::query()->create([
            'name' => 'Staff First Company', 'slug' => 'staff-first-company',
            'invoice_prefix' => 'SFC', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        $secondCompany = Company::query()->create([
            'name' => 'Staff Second Company', 'slug' => 'staff-second-company',
            'invoice_prefix' => 'SSC', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        $user = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $user->companies()->sync([
            $firstCompany->getKey() => ['role' => 'manager', 'is_default' => true],
            $secondCompany->getKey() => ['role' => 'manager', 'is_default' => false],
        ]);

        $this->actingAs($user)
            ->withSession(['current_company_id' => $firstCompany->getKey()])
            ->get('/admin')
            ->assertSessionHas('current_company_id', $firstCompany->getKey());

        $this
            ->post(route('admin.company.switch'), ['company_id' => $secondCompany->getKey()])
            ->assertRedirect()
            ->assertSessionHas('current_company_id', $secondCompany->getKey());

        $this
            ->get('/admin')
            ->assertOk()
            ->assertSessionHas('current_company_id', $secondCompany->getKey());

        $this->assertSame($secondCompany->getKey(), app(CompanyContext::class)->id());
    }

    public function test_admin_pages_are_never_cached_so_a_switched_company_cannot_show_a_stale_page(): void
    {
        [$user] = $this->superAdminWithTwoCompanies();

        $response = $this->actingAs($user)->get('/admin');

        // Symfony's ResponseHeaderBag parses Cache-Control into directive
        // flags and re-serializes them (alphabetically, with an implied
        // max-age=0 added for no-cache) rather than keeping our literal
        // string - the assertions below check the individual directives
        // that actually matter instead of a fragile exact string match.
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $response->assertHeader('Pragma', 'no-cache');
    }

    public function test_legacy_automatic_all_companies_session_migrates_to_the_default_company(): void
    {
        [$user, , $defaultCompany] = $this->superAdminWithTwoCompanies();

        $this->actingAs($user)
            ->withSession(['current_company_id' => 'all'])
            ->get('/admin')
            ->assertOk()
            ->assertSessionHas('current_company_id', $defaultCompany->getKey())
            ->assertSessionHas('current_company_selection_explicit', false);
    }

    /** @return array{User, Company, Company} */
    private function superAdminWithTwoCompanies(): array
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $firstCompany = $user->defaultCompany();
        $defaultCompany = Company::query()->create([
            'name' => 'Preferred Company',
            'slug' => 'preferred-company',
            'invoice_prefix' => 'PRF',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        $user->companies()->sync([
            $firstCompany->getKey() => [
                'role' => 'super_admin',
                'is_default' => false,
            ],
            $defaultCompany->getKey() => [
                'role' => 'super_admin',
                'is_default' => true,
            ],
        ]);

        return [$user, $firstCompany, $defaultCompany];
    }
}
