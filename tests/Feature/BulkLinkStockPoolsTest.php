<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\StockPools\Pages\BulkLinkStockPools;
use App\Filament\Resources\StockPools\Pages\ListStockPools;
use App\Filament\Resources\StockPools\StockPoolResource;
use App\Models\Company;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockPool;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Superadmin bulk tool that links many products across two companies into
 * shared stock pools in one save, reusing StockPoolResource::syncMembers().
 */
class BulkLinkStockPoolsTest extends TestCase
{
    use RefreshDatabase;

    protected Company $international;

    protected Company $gadget;

    protected Product $sourceA;

    protected Product $sourceB;

    protected Product $targetA;

    protected Product $targetB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->international = Company::query()->create([
            'name' => 'ZamZam International', 'slug' => 'zi-bulk-link',
            'invoice_prefix' => 'ZIBL', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        $this->gadget = Company::query()->create([
            'name' => 'ZamZam Gadget', 'slug' => 'zg-bulk-link',
            'invoice_prefix' => 'ZGBL', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);

        app(CompanyContext::class)->set($this->international);
        $this->sourceA = Product::query()->create(['name' => 'Power Bank', 'sku' => 'PB-100', 'price' => 500, 'sale_price' => 500, 'stock' => 0]);
        $this->sourceB = Product::query()->create(['name' => 'USB Cable', 'sku' => 'USB-200', 'price' => 100, 'sale_price' => 100, 'stock' => 0]);
        StockMovement::query()->create(['product_id' => $this->sourceA->id, 'type' => 'opening', 'quantity' => 15]);
        StockMovement::query()->create(['product_id' => $this->sourceB->id, 'type' => 'opening', 'quantity' => 40]);

        app(CompanyContext::class)->set($this->gadget);
        $this->targetA = Product::query()->create(['name' => 'Power Bank Retail', 'sku' => 'PB-100', 'price' => 650, 'sale_price' => 650, 'stock' => 0]);
        $this->targetB = Product::query()->create(['name' => 'USB Cable Retail', 'sku' => 'USB-200-R', 'price' => 150, 'sale_price' => 150, 'stock' => 0]);

        app(CompanyContext::class)->all();
        $this->actingAs(User::factory()->create(['role' => 'super_admin', 'is_active' => true]));
    }

    public function test_non_super_admin_cannot_access_the_bulk_link_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'sales_staff', 'is_active' => true]));

        $this->assertFalse(BulkLinkStockPools::canAccess());
    }

    public function test_staged_matches_are_linked_into_shared_pools_on_save(): void
    {
        Livewire::test(BulkLinkStockPools::class)
            ->set('sourceCompanyId', $this->international->getKey())
            ->set('targetCompanyId', $this->gadget->getKey())
            ->assertCanSeeTableRecords([$this->sourceA, $this->sourceB])
            ->call('updateTableColumnState', 'match', (string) $this->sourceA->getKey(), (string) $this->targetA->getKey())
            ->call('updateTableColumnState', 'match', (string) $this->sourceB->getKey(), (string) $this->targetB->getKey())
            ->assertSet("links.{$this->sourceA->getKey()}", $this->targetA->getKey())
            ->callAction('saveLinks')
            ->assertHasNoActionErrors()
            ->assertSet('links', []);

        // Two independent pools, source products own them.
        $this->assertSame(2, StockPool::query()->count());

        $this->sourceA->refresh();
        $this->targetA->refresh();
        $this->assertNotNull($this->sourceA->stock_pool_id);
        $this->assertSame($this->sourceA->stock_pool_id, $this->targetA->stock_pool_id);
        $this->assertSame(15, $this->sourceA->stock);
        $this->assertSame(15, $this->targetA->stock);

        $this->sourceB->refresh();
        $this->targetB->refresh();
        $this->assertSame($this->sourceB->stock_pool_id, $this->targetB->stock_pool_id);
        $this->assertSame(40, $this->targetB->stock);
    }

    public function test_linking_to_an_already_pooled_source_adds_to_the_existing_pool(): void
    {
        $pool = StockPool::query()->create(['source_product_id' => $this->sourceA->getKey()]);
        StockPoolResource::syncMembers($pool, [$this->targetA->getKey()]);

        Livewire::test(BulkLinkStockPools::class)
            ->set('sourceCompanyId', $this->international->getKey())
            ->set('targetCompanyId', $this->gadget->getKey())
            ->call('updateTableColumnState', 'match', (string) $this->sourceA->getKey(), (string) $this->targetB->getKey())
            ->callAction('saveLinks')
            ->assertHasNoActionErrors();

        $this->assertSame(1, StockPool::query()->count());

        $this->targetB->refresh();
        $this->assertSame($pool->getKey(), $this->targetB->stock_pool_id);
        // Whole pool now shares sourceA's ledger total.
        $this->assertSame(15, $this->targetB->stock);
        $this->assertSame(15, $this->targetA->refresh()->stock);
    }

    public function test_products_list_and_pool_list_surface_the_pooling_company(): void
    {
        $pool = StockPool::query()->create(['source_product_id' => $this->sourceA->getKey()]);
        StockPoolResource::syncMembers($pool, [$this->targetA->getKey()]);

        app(CompanyContext::class)->set($this->international);
        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords([$this->sourceA])
            ->assertSee('ZamZam Gadget');

        app(CompanyContext::class)->all();
        Livewire::test(ListStockPools::class)
            ->assertCanSeeTableRecords([$pool])
            ->assertSee('ZamZam International')
            ->assertSee('ZamZam Gadget');
    }

    public function test_suggest_matches_prefills_links_by_exact_sku(): void
    {
        Livewire::test(BulkLinkStockPools::class)
            ->set('sourceCompanyId', $this->international->getKey())
            ->set('targetCompanyId', $this->gadget->getKey())
            ->callAction('suggestMatches')
            ->assertHasNoActionErrors()
            // PB-100 matches PB-100 across companies; USB-200 has no exact match.
            ->assertSet("links.{$this->sourceA->getKey()}", $this->targetA->getKey())
            ->assertSet("links.{$this->sourceB->getKey()}", null);
    }
}
