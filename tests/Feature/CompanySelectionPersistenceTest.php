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
