<?php

namespace Tests\Feature;

use App\Filament\Widgets\BusinessOverview;
use App\Filament\Widgets\CourierHealthWidget;
use App\Filament\Widgets\CustomerRiskOverview;
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

    public function test_dashboard_stat_cards_do_not_render_secondary_descriptions(): void
    {
        $company = Company::query()->create([
            'name' => 'Dashboard Stat Company',
            'slug' => 'dashboard-stat-company',
            'invoice_prefix' => 'DSC',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        app(CompanyContext::class)->set($company);

        $widgets = [
            new class extends BusinessOverview
            {
                public function statsForTest(): array
                {
                    return $this->getStats();
                }
            },
            new class extends CustomerRiskOverview
            {
                public function statsForTest(): array
                {
                    return $this->getStats();
                }
            },
            new class extends CourierHealthWidget
            {
                public function statsForTest(): array
                {
                    return $this->getStats();
                }
            },
        ];

        foreach ($widgets as $widget) {
            foreach ($widget->statsForTest() as $stat) {
                $this->assertNull($stat->getDescription());
                $this->assertNull($stat->getDescriptionIcon());
            }
        }
    }

    public function test_business_overview_money_values_omit_trailing_decimal_zeroes(): void
    {
        $widget = new class extends BusinessOverview
        {
            public function formatMoney(float|int|string $amount): string
            {
                return $this->money($amount);
            }
        };

        $this->assertSame('BDT 0', $widget->formatMoney(0));
        $this->assertSame('BDT 1,625,000', $widget->formatMoney(1625000));
        $this->assertSame('BDT 2,520.5', $widget->formatMoney(2520.50));
        $this->assertSame('BDT 2,520.25', $widget->formatMoney(2520.25));
    }
}
