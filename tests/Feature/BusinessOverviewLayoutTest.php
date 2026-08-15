<?php

namespace Tests\Feature;

use App\Filament\Widgets\BusinessOverview;
use App\Models\Company;
use App\Services\CompanyContext;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BusinessOverviewLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_overview_uses_the_compact_responsive_card_layout(): void
    {
        $company = Company::query()->create([
            'name' => 'Dashboard Layout Company',
            'slug' => 'dashboard-layout-company',
            'invoice_prefix' => 'DLC',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        app(CompanyContext::class)->set($company);

        $widget = new class extends BusinessOverview
        {
            /** @return array<string, int> */
            public function layoutColumns(): array
            {
                return $this->getColumns();
            }

            /** @return array<Stat> */
            public function overviewStats(): array
            {
                return $this->getStats();
            }
        };

        $this->assertSame([
            'default' => 2,
            'lg' => 5,
        ], $widget->layoutColumns());

        foreach ($widget->overviewStats() as $stat) {
            $this->assertSame('zz-business-overview-stat', $stat->getExtraAttributes()['class'] ?? null);
        }

        $theme = file_get_contents(resource_path('css/filament/admin/theme.css'));

        $this->assertIsString($theme);
        $this->assertStringContainsString(".zz-business-overview-stat {\n    padding: 10px;", $theme);
        $this->assertStringContainsString(".zz-business-overview-stat .fi-wi-stats-overview-stat-value {\n    font-size: 20px;", $theme);

        Livewire::test(BusinessOverview::class)
            ->assertSeeHtml('zz-business-overview-stat')
            ->assertSeeHtml('--cols-default: repeat(2, minmax(0, 1fr))')
            ->assertSeeHtml('--cols-lg: repeat(5, minmax(0, 1fr))');
    }
}
