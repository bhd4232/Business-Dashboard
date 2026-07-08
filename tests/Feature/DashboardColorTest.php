<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\DynamicColorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardColorTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_panel_injects_the_current_companys_dashboard_color(): void
    {
        $company = Company::query()->create([
            'name' => 'Blue Traders', 'slug' => 'blue-traders', 'invoice_prefix' => 'BLU',
            'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
            'dashboard_color' => '#2563EB',
        ]);

        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $admin->companies()->attach($company->getKey());

        $expected = app(DynamicColorService::class)->generateShades('#2563EB');

        $this->actingAs($admin)
            ->withSession(['current_company_id' => $company->getKey()])
            ->get('/admin')
            ->assertOk()
            ->assertSee($expected[500], false);
    }

    public function test_switching_company_changes_the_injected_color_without_a_deploy(): void
    {
        $companyA = Company::query()->create([
            'name' => 'Red Co', 'slug' => 'red-co', 'invoice_prefix' => 'RED',
            'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
            'dashboard_color' => '#DC2626',
        ]);
        $companyB = Company::query()->create([
            'name' => 'Green Co', 'slug' => 'green-co', 'invoice_prefix' => 'GRN',
            'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
            'dashboard_color' => '#16A34A',
        ]);

        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $admin->companies()->attach([$companyA->getKey(), $companyB->getKey()]);

        $service = app(DynamicColorService::class);
        $redShades = $service->generateShades('#DC2626');
        $greenShades = $service->generateShades('#16A34A');

        $this->actingAs($admin)
            ->withSession(['current_company_id' => $companyA->getKey()])
            ->get('/admin')
            ->assertOk()
            ->assertSee($redShades[500], false)
            ->assertDontSee($greenShades[500], false);

        $this->actingAs($admin)
            ->withSession(['current_company_id' => $companyB->getKey()])
            ->get('/admin')
            ->assertOk()
            ->assertSee($greenShades[500], false)
            ->assertDontSee($redShades[500], false);
    }

    public function test_all_companies_view_falls_back_to_the_default_color(): void
    {
        Company::query()->create([
            'name' => 'Purple Co', 'slug' => 'purple-co', 'invoice_prefix' => 'PUR',
            'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
            'dashboard_color' => '#7C3AED',
        ]);

        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $default = app(DynamicColorService::class)->generateShades(DynamicColorService::DEFAULT_COLOR);
        $purple = app(DynamicColorService::class)->generateShades('#7C3AED');

        $this->actingAs($admin)
            ->withSession(['current_company_id' => 'all'])
            ->get('/admin')
            ->assertOk()
            ->assertSee($default[500], false)
            ->assertDontSee($purple[500], false);
    }

    public function test_dynamic_color_service_generates_a_full_shade_ladder(): void
    {
        $shades = app(DynamicColorService::class)->generateShades('#F59E0B');

        foreach ([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950] as $shade) {
            $this->assertArrayHasKey($shade, $shades);
            $this->assertNotSame('', $shades[$shade]);
        }
    }

    public function test_dynamic_color_service_falls_back_to_default_for_an_invalid_hex(): void
    {
        $default = app(DynamicColorService::class)->generateShades(DynamicColorService::DEFAULT_COLOR);
        $invalid = app(DynamicColorService::class)->generateShades('not-a-hex');

        $this->assertSame($default, $invalid);
    }
}
