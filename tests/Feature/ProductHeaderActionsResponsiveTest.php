<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyContext;
use Filament\Actions\ActionGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductHeaderActionsResponsiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_uses_one_native_action_menu_and_desktop_keeps_individual_buttons(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $component = Livewire::test(ListProducts::class)
            ->assertActionExists('importCsv')
            ->assertActionExists('downloadSampleCsv')
            ->assertActionExists('bulkUpdateStock')
            ->assertActionExists('exportCsv')
            ->assertActionExists('create')
            ->assertActionExists('mobileImportCsv')
            ->assertActionExists('mobileDownloadSampleCsv')
            ->assertActionExists('mobileBulkUpdateStock')
            ->assertActionExists('mobileExportCsv')
            ->assertActionExists('mobileCreate')
            ->assertSeeHtml('zz-products-mobile-actions')
            ->assertSeeHtml('lg:!hidden')
            ->assertSeeHtml('!hidden lg:!inline-grid');

        $groups = collect($component->instance()->getCachedHeaderActions())
            ->filter(fn (mixed $action): bool => $action instanceof ActionGroup);

        $this->assertCount(1, $groups);

        /** @var ActionGroup $mobileMenu */
        $mobileMenu = $groups->first();

        $this->assertTrue($mobileMenu->isIconButton());
        $this->assertSame('Product actions', $mobileMenu->getLabel());
        $this->assertSame('heroicon-o-ellipsis-vertical', $mobileMenu->getIcon());
        $this->assertSame('bottom-end', $mobileMenu->getDropdownPlacement());
        $this->assertSame(
            ['mobileImportCsv', 'mobileDownloadSampleCsv', 'mobileBulkUpdateStock', 'mobileExportCsv', 'mobileCreate'],
            array_keys($mobileMenu->getFlatActions()),
        );
    }

    public function test_products_header_has_mobile_side_by_side_alignment_scoped_to_its_menu(): void
    {
        $theme = file_get_contents(resource_path('css/filament/admin/theme.css'));

        $this->assertStringContainsString('@media (max-width: 63.999rem)', $theme);
        $this->assertStringContainsString('.fi-header:has(.zz-products-mobile-actions)', $theme);
        $this->assertStringContainsString('justify-content: space-between', $theme);
    }

    /** @return array{0: Company, 1: User} */
    protected function makeCompanyAndUser(): array
    {
        $company = Company::query()->create([
            'name' => 'Responsive Product Header Co',
            'slug' => 'responsive-product-header-co',
            'invoice_prefix' => 'RPH',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);

        $user = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        return [$company, $user];
    }
}
