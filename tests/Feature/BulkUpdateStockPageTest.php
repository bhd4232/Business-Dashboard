<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\BulkUpdateStock;
use App\Models\Company;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The owner's replacement for the CSV-upload "Bulk Update Stock" modal: a
 * searchable/filterable list of every product with an inline-editable New
 * Stock field, plus a `?shortageOnly=1` deep link from the Total Shortage
 * stat card.
 */
class BulkUpdateStockPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_products_with_stock_and_short_by(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $healthy = $this->makeProduct('Healthy Product', stock: 20, reorderLevel: 5);
        $short = $this->makeProduct('Short Product', stock: 2, reorderLevel: 10);

        Livewire::test(BulkUpdateStock::class)
            ->assertCanSeeTableRecords([$healthy, $short])
            ->assertTableColumnStateSet('short_by', 0, $healthy)
            ->assertTableColumnStateSet('short_by', 8, $short)
            ->assertTableColumnStateSet('new_stock', null, $healthy)
            ->assertTableColumnStateSet('new_stock', null, $short);
    }

    public function test_shortage_only_query_param_scopes_the_table(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $healthy = $this->makeProduct('Healthy Product', stock: 20, reorderLevel: 5);
        $short = $this->makeProduct('Short Product', stock: 2, reorderLevel: 10);

        Livewire::test(BulkUpdateStock::class)
            ->set('shortageOnly', true)
            ->assertCanSeeTableRecords([$short])
            ->assertCanNotSeeTableRecords([$healthy]);
    }

    public function test_new_stock_is_staged_until_save_changes_then_updates_via_a_stock_movement(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $product = $this->makeProduct('Editable Product', stock: 5, reorderLevel: 2);

        $component = Livewire::test(BulkUpdateStock::class)
            ->assertActionExists('saveChanges')
            ->assertActionDoesNotExist('importStockCsv')
            ->assertActionDoesNotExist('downloadStockSampleCsv')
            ->assertTableColumnStateSet('new_stock', null, $product)
            ->call('updateTableColumnState', 'new_stock', (string) $product->getKey(), '12')
            ->assertSet("stockUpdates.{$product->getKey()}", 12);

        $this->assertSame(5, $product->refresh()->stock);
        $this->assertDatabaseMissing('stock_movements', [
            'product_id' => $product->id,
            'type' => 'adjustment',
        ]);

        $component
            ->callAction('saveChanges')
            ->assertHasNoActionErrors()
            ->assertSet('stockUpdates', [])
            ->assertTableColumnStateSet('new_stock', null, $product);

        $this->assertSame(12, $product->refresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'adjustment',
            'quantity' => 7,
        ]);
    }

    public function test_save_changes_applies_multiple_staged_rows_and_leaves_blank_rows_untouched(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $first = $this->makeProduct('First Batch Product', stock: 5, reorderLevel: 2);
        $second = $this->makeProduct('Second Batch Product', stock: 10, reorderLevel: 2);
        $blank = $this->makeProduct('Blank Batch Product', stock: 15, reorderLevel: 2);

        Livewire::test(BulkUpdateStock::class)
            ->call('updateTableColumnState', 'new_stock', (string) $first->getKey(), '7')
            ->call('updateTableColumnState', 'new_stock', (string) $second->getKey(), '20')
            ->callAction('saveChanges')
            ->assertHasNoActionErrors()
            ->assertSet('stockUpdates', []);

        $this->assertSame(7, $first->refresh()->stock);
        $this->assertSame(20, $second->refresh()->stock);
        $this->assertSame(15, $blank->refresh()->stock);
    }

    public function test_a_product_from_another_company_is_not_visible(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();

        $otherCompany = Company::query()->create([
            'name' => 'Other Bulk Co', 'slug' => 'other-bulk-co',
            'invoice_prefix' => 'OBC', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        app(CompanyContext::class)->set($otherCompany);
        $otherProduct = $this->makeProduct('Other Company Product', stock: 5, reorderLevel: 2);

        app(CompanyContext::class)->set($company);
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Livewire::test(BulkUpdateStock::class)
            ->assertCanNotSeeTableRecords([$otherProduct]);
    }

    /** @return array{0: Company, 1: User} */
    protected function makeCompanyAndUser(): array
    {
        $company = Company::query()->create([
            'name' => 'Bulk Stock Co', 'slug' => 'bulk-stock-co',
            'invoice_prefix' => 'BSC', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);

        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        return [$company, $user];
    }

    protected function makeProduct(string $name, int $stock, int $reorderLevel): Product
    {
        $product = Product::query()->create([
            'name' => $name,
            'sku' => 'BULK-'.str($name)->slug()->upper(),
            'price' => 500,
            'sale_price' => 450,
            'cost_price' => 300,
            'stock' => 0,
            'reorder_level' => $reorderLevel,
            'unit' => 'pcs',
            'vat_rate' => 0,
            'is_active' => true,
        ]);

        StockMovement::query()->create([
            'product_id' => $product->id,
            'type' => 'opening',
            'quantity' => $stock,
        ]);

        return $product->refresh();
    }
}
