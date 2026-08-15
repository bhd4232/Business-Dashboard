<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Widgets\ProductStatsOverview;
use App\Models\Company;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The stat cards at the top of the Products list, matching the ones shown
 * on the competitor ERP's "Products & Pricing" and "Inventory" pages:
 * Total SKU, Total Variants, New SKU, Active SKU, Inactive SKU, Total
 * Available Quantity, Total Stock Value, Total Purchase Cost. Every count
 * sums the equivalent Product + ProductVariant rows, since a variant-
 * bearing product contributes one base-product SKU plus one SKU per
 * variant.
 */
class ProductStatsOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_stat_grid_uses_the_full_header_width_with_the_requested_responsive_layout(): void
    {
        $company = Company::query()->create([
            'name' => 'Product Layout Company',
            'slug' => 'product-layout-company',
            'invoice_prefix' => 'PLC',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);

        $widget = new ProductStatsOverview;
        $view = file_get_contents(resource_path('views/filament/resources/products/widgets/product-stats-overview.blade.php'));

        $this->assertSame('full', $widget->getColumnSpan());
        $this->assertStringContainsString('<x-filament-widgets::widget>', $view);
        $this->assertStringContainsString('grid-cols-2', $view);
        $this->assertStringContainsString('lg:grid-cols-5', $view);
        $this->assertStringContainsString('!p-[10px]', $view);
        $this->assertStringContainsString('!text-xs lg:!text-sm', $view);
        $this->assertStringContainsString('!text-base lg:!text-[20px]', $view);

        Livewire::test(ProductStatsOverview::class)
            ->assertSeeHtml('fi-wi-widget')
            ->assertSeeHtml('--col-span-lg: 1 / -1')
            ->assertSeeHtml('grid-cols-2')
            ->assertSeeHtml('lg:grid-cols-5')
            ->assertSeeHtml('!text-xs lg:!text-sm')
            ->assertSeeHtml('!text-base lg:!text-[20px]');
    }

    public function test_currency_cards_use_the_taka_symbol_and_only_show_decimals_for_fractional_values(): void
    {
        $widget = new ProductStatsOverview;

        $this->assertSame('৳ 2,149,140', $widget->formatCurrency(2149140.00));
        $this->assertSame('৳ 360,175.50', $widget->formatCurrency(360175.5));
        $this->assertSame('৳ 99.25', $widget->formatCurrency(99.254));
    }

    public function test_stat_cards_count_products_and_variants_correctly(): void
    {
        $company = Company::query()->create([
            'name' => 'Stats Co', 'slug' => 'stats-co',
            'invoice_prefix' => 'STC', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);

        // P1: active, created recently -> counts as New + Active.
        $this->makeProduct('P1', isActive: true);

        // P2: inactive, backdated -> not new, not active.
        $this->backdate($this->makeProduct('P2', isActive: false));

        // P3: variant-bearing base product, active, backdated (not new).
        $p3 = $this->makeProduct('P3', isActive: true, hasVariants: true);
        $this->backdate($p3);

        // V1: active, created recently -> New + Active.
        $p3->variants()->create(['options' => ['Size' => 'M'], 'stock' => 5, 'is_active' => true]);

        // V2: inactive, backdated -> not new, not active.
        $v2 = $p3->variants()->create(['options' => ['Size' => 'L'], 'stock' => 5, 'is_active' => false]);
        $this->backdate($v2);

        // A second company's data must never leak into these counts.
        $otherCompany = Company::query()->create([
            'name' => 'Other Stats Co', 'slug' => 'other-stats-co',
            'invoice_prefix' => 'OST', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        app(CompanyContext::class)->set($otherCompany);
        $this->makeProduct('Other Co Product', isActive: true);

        app(CompanyContext::class)->set($company);

        // Totals for $company: 3 products + 2 variants = 5 SKUs.
        // Variants: 2. New: P1 + V1 = 2. Active: P1 + P3 + V1 = 3. Inactive: P2 + V2 = 2.
        $stats = $this->computeStats();

        $this->assertSame(5, $stats['Total SKU']);
        $this->assertSame(2, $stats['Total Variants']);
        $this->assertSame(2, $stats['New SKU']);
        $this->assertSame(3, $stats['Active SKU']);
        $this->assertSame(2, $stats['Inactive SKU']);
    }

    public function test_quantity_value_and_purchase_cost_cards_avoid_double_counting_and_scope_by_purchase_status(): void
    {
        $company = Company::query()->create([
            'name' => 'Stats Co', 'slug' => 'stats-co',
            'invoice_prefix' => 'STC', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);

        // Simple products: own stock/cost_price count directly.
        $this->makeProduct('P1', isActive: true); // stock 5, cost_price 300
        $this->makeProduct('P2', isActive: true); // stock 5, cost_price 300

        // Variant-bearing product: its own mirrored `stock` must NOT be
        // added again on top of its variants' stock.
        $p3 = $this->makeProduct('P3', isActive: true, hasVariants: true);
        $p3->variants()->create(['options' => ['Size' => 'M'], 'stock' => 5, 'is_active' => true]); // no own cost_price -> falls back to P3's 300
        $p3->variants()->create(['options' => ['Size' => 'L'], 'stock' => 5, 'is_active' => false]);

        // P4: a received purchase re-derives its stock from the movement
        // ledger (3, not the original directly-set 5), and its landed cost
        // (subtotal + allocated extra costs) must count toward Total
        // Purchase Cost.
        $p4 = $this->makeProduct('P4', isActive: true);
        $supplier = Supplier::query()->create(['name' => 'Stats Supplier']);

        $receivedPurchase = Purchase::query()->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now(),
            'freight_to_ctg' => 20,
            'duty' => 10,
            'status' => 'received',
            'update_cost_price' => false,
        ]);
        PurchaseItem::query()->create([
            'purchase_id' => $receivedPurchase->id,
            'product_id' => $p4->id,
            'quantity' => 3,
            'unit_cost' => 100,
        ]);

        // Draft purchase must be excluded from Total Purchase Cost entirely
        // (and never touches stock, since it's not received).
        $draftPurchase = Purchase::query()->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now(),
            'status' => 'draft',
        ]);
        PurchaseItem::query()->create([
            'purchase_id' => $draftPurchase->id,
            'product_id' => $p4->id,
            'quantity' => 1,
            'unit_cost' => 999999,
        ]);

        $this->assertSame(3, $p4->refresh()->stock);

        $stats = $this->computeStats();

        // Available: P1(5) + P2(5) + V1(5) + V2(5) + P4(3) = 23. P3's own
        // mirrored stock (5) is excluded to avoid double-counting V1+V2.
        $this->assertSame(23, $stats['Total Available Quantity']);

        // Value: (5+5+5+5+3) x 300 cost_price = 6900.
        $this->assertEqualsWithDelta(6900.0, $stats['Total Stock Value'], 0.01);

        // Purchase cost: only the received purchase's item, subtotal(300) +
        // allocated_cost(30 extra) = 330. Draft purchase's 999999 unit_cost
        // must not appear anywhere in this total.
        $this->assertEqualsWithDelta(330.0, $stats['Total Purchase Cost'], 0.01);
    }

    public function test_shortage_and_damage_cards_use_real_existing_data(): void
    {
        $company = Company::query()->create([
            'name' => 'Stats Co', 'slug' => 'stats-co',
            'invoice_prefix' => 'STC', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);

        // Healthy: stock well above its reorder level.
        Product::query()->create([
            'name' => 'Healthy', 'sku' => 'STAT-HEALTHY',
            'price' => 100, 'sale_price' => 100, 'stock' => 20, 'reorder_level' => 5, 'is_active' => true,
        ]);

        // Shortage: stock at/below its reorder level — same predicate the
        // existing "Low stock" table filter already uses.
        $short = Product::query()->create([
            'name' => 'Short', 'sku' => 'STAT-SHORT',
            'price' => 100, 'sale_price' => 100, 'stock' => 0, 'reorder_level' => 5, 'is_active' => true,
        ]);
        StockMovement::query()->create(['product_id' => $short->id, 'type' => 'opening', 'quantity' => 2]);

        // Exactly at the threshold also counts (whereColumn '<=').
        $atThreshold = Product::query()->create([
            'name' => 'At Threshold', 'sku' => 'STAT-AT-THRESHOLD',
            'price' => 100, 'sale_price' => 100, 'stock' => 0, 'reorder_level' => 4, 'is_active' => true,
        ]);
        StockMovement::query()->create(['product_id' => $atThreshold->id, 'type' => 'opening', 'quantity' => 4]);

        // Two damage movements against a well-stocked product.
        $damaged = Product::query()->create([
            'name' => 'Damaged', 'sku' => 'STAT-DAMAGED',
            'price' => 100, 'sale_price' => 100, 'stock' => 0, 'reorder_level' => 1, 'is_active' => true,
        ]);
        StockMovement::query()->create(['product_id' => $damaged->id, 'type' => 'opening', 'quantity' => 20]);
        StockMovement::query()->create(['product_id' => $damaged->id, 'type' => 'damage', 'quantity' => -3, 'reason' => 'Crushed box']);
        StockMovement::query()->create(['product_id' => $damaged->id, 'type' => 'damage', 'quantity' => -2, 'reason' => 'Water damage']);

        $stats = $this->computeStats();

        $this->assertSame(2, $stats['Total Shortage']);
        $this->assertSame(5, $stats['Total Damage']);
    }

    /** @return array<string, int|float> */
    protected function computeStats(): array
    {
        return collect((new ProductStatsOverview)->stats())
            ->mapWithKeys(fn (array $stat): array => [$stat['label'] => $stat['value']])
            ->all();
    }

    protected function makeProduct(string $name, bool $isActive, bool $hasVariants = false): Product
    {
        return Product::query()->create([
            'name' => $name,
            'sku' => 'STAT-'.str($name)->slug()->upper(),
            'price' => 500,
            'sale_price' => 450,
            'cost_price' => 300,
            'stock' => 5,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => $isActive,
            'status' => Product::STATUS_AVAILABLE,
            'has_variants' => $hasVariants,
            'variant_attributes' => $hasVariants ? ['Size' => ['M', 'L']] : null,
        ]);
    }

    protected function backdate(mixed $model): void
    {
        $model->forceFill(['created_at' => now()->subDays(10)])->saveQuietly();
    }
}
