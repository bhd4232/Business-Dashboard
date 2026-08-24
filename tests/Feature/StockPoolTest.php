<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockPool;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockPoolTest extends TestCase
{
    use RefreshDatabase;

    public function test_selling_in_the_non_source_company_updates_shared_live_stock_in_both_companies(): void
    {
        [$source, $mirror] = $this->createPooledProducts(openingStock: 20);

        $sale = StockMovement::query()->create([
            'product_id' => $mirror->id,
            'type' => 'sale',
            'quantity' => 5,
        ]);

        $this->assertSame(15, $source->refresh()->stock);
        $this->assertSame(15, $mirror->refresh()->stock);

        // The mirror's own ledger nets to zero (pass-through): the sale
        // (-5) is offset by an auto-generated transfer (+5).
        $mirrorTransfer = StockMovement::withoutGlobalScopes()
            ->where('product_id', $mirror->id)
            ->where('type', 'transfer')
            ->where('reference_type', StockMovement::class)
            ->where('reference_id', $sale->id)
            ->first();

        $this->assertNotNull($mirrorTransfer);
        $this->assertSame(5, $mirrorTransfer->quantity);

        // The source carries the real, negative effect instead.
        $sourceTransfer = StockMovement::withoutGlobalScopes()
            ->where('product_id', $source->id)
            ->where('type', 'transfer')
            ->where('reference_type', StockMovement::class)
            ->where('reference_id', $sale->id)
            ->first();

        $this->assertNotNull($sourceTransfer);
        $this->assertSame(-5, $sourceTransfer->quantity);
    }

    public function test_sale_in_the_non_source_company_is_blocked_when_the_shared_pool_is_insufficient(): void
    {
        [, $mirror] = $this->createPooledProducts(openingStock: 3);

        $this->expectException(ValidationException::class);

        // The mirror's OWN ledger has zero movements of its own — this must
        // still be blocked because it checks the pool's combined total, not
        // just this product's own (empty) history.
        StockMovement::query()->create([
            'product_id' => $mirror->id,
            'type' => 'sale',
            'quantity' => 5,
        ]);
    }

    public function test_deleting_the_triggering_sale_removes_the_mirrored_transfer_and_restores_stock(): void
    {
        [$source, $mirror] = $this->createPooledProducts(openingStock: 20);

        $sale = StockMovement::query()->create([
            'product_id' => $mirror->id,
            'type' => 'sale',
            'quantity' => 5,
        ]);

        $this->assertSame(15, $source->refresh()->stock);

        $sale->delete();

        $this->assertSame(20, $source->refresh()->stock);
        $this->assertSame(20, $mirror->refresh()->stock);

        $this->assertSame(
            0,
            StockMovement::withoutGlobalScopes()->where('type', 'transfer')->count(),
        );
    }

    public function test_selling_directly_against_the_source_product_does_not_create_a_transfer(): void
    {
        [$source] = $this->createPooledProducts(openingStock: 20);

        app(CompanyContext::class)->set($source->company);

        StockMovement::query()->create([
            'product_id' => $source->id,
            'type' => 'sale',
            'quantity' => 5,
        ]);

        $this->assertSame(
            0,
            StockMovement::withoutGlobalScopes()->where('type', 'transfer')->count(),
        );
    }

    /** @return array{0: Product, 1: Product} [source, mirror] */
    private function createPooledProducts(int $openingStock): array
    {
        $international = Company::query()->create([
            'name' => 'ZamZam International',
            'slug' => 'zamzam-international-pool-test',
            'invoice_prefix' => 'ZINT',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        $gadget = Company::query()->create([
            'name' => 'ZamZam Gadget',
            'slug' => 'zamzam-gadget-pool-test',
            'invoice_prefix' => 'ZGDT',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        app(CompanyContext::class)->set($international);
        $source = Product::query()->create([
            'name' => 'Shared Widget',
            'sku' => 'SHARED-001',
            'price' => 500,
            'sale_price' => 500,
            'stock' => 0,
        ]);

        app(CompanyContext::class)->set($gadget);
        $mirror = Product::query()->create([
            'name' => 'Shared Widget (Retail)',
            'sku' => 'SHARED-001-R',
            'price' => 600,
            'sale_price' => 600,
            'stock' => 0,
        ]);

        $pool = StockPool::query()->create(['source_product_id' => $source->id]);
        $source->update(['stock_pool_id' => $pool->id]);
        $mirror->update(['stock_pool_id' => $pool->id]);

        app(CompanyContext::class)->set($international);
        StockMovement::query()->create([
            'product_id' => $source->id,
            'type' => 'opening',
            'quantity' => $openingStock,
        ]);

        app(CompanyContext::class)->set($gadget);

        return [$source->refresh(), $mirror->refresh()];
    }
}
