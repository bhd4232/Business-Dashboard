<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CompanyContext;
use App\Services\ProductCsvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * "Bulk Update Stock" — a focused, sku+stock-only sibling of the full
 * Import CSV, matching the competitor ERP's dedicated Inventory-page
 * "Bulk Update Inventory" tool. SKUs are matched against both `Product`
 * and `ProductVariant`, since a variant carries its own SKU.
 */
class ProductStockCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_csv_sample_can_be_downloaded(): void
    {
        $user = User::factory()->create(['role' => 'inventory_staff', 'is_active' => true]);

        $response = $this->actingAs($user)->get(route('products.stock.sample'));

        $response->assertOk();
        $response->assertDownload('products-stock-sample.csv');
        $this->assertStringContainsString('sku,stock', $response->streamedContent());
    }

    public function test_updates_a_simple_products_stock_via_stock_movement(): void
    {
        Storage::fake('local');

        $product = Product::query()->create([
            'name' => 'Simple Stock Product', 'sku' => 'STK-SIMPLE-001',
            'price' => 100, 'sale_price' => 100, 'stock' => 0,
        ]);
        $product->setStockFromProductForm(5);

        $path = $this->writeCsv(['STK-SIMPLE-001,12']);

        $result = app(ProductCsvService::class)->importStock($path);

        $this->assertSame(['updated' => 1], $result);
        $this->assertSame(12, $product->refresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'adjustment',
            'quantity' => 7,
        ]);
    }

    public function test_updates_a_variant_stock_and_cascades_to_the_parent_product(): void
    {
        Storage::fake('local');

        $product = Product::query()->create([
            'name' => 'Variant Parent', 'sku' => 'STK-PARENT-001',
            'price' => 100, 'sale_price' => 100, 'stock' => 0, 'has_variants' => true,
        ]);
        $variantA = $product->variants()->create(['sku' => 'STK-VARIANT-A', 'options' => ['Size' => 'M'], 'stock' => 5, 'is_active' => true]);
        $product->variants()->create(['sku' => 'STK-VARIANT-B', 'options' => ['Size' => 'L'], 'stock' => 3, 'is_active' => true]);

        $path = $this->writeCsv(['STK-VARIANT-A,20']);

        $result = app(ProductCsvService::class)->importStock($path);

        $this->assertSame(['updated' => 1], $result);
        $this->assertSame(20, $variantA->refresh()->stock);
        // Parent mirrors the sum of active variant stock: 20 + 3 = 23.
        $this->assertSame(23, $product->refresh()->stock);
    }

    public function test_rejects_a_variant_bearing_products_own_sku(): void
    {
        Storage::fake('local');

        $product = Product::query()->create([
            'name' => 'Variant Parent', 'sku' => 'STK-PARENT-002',
            'price' => 100, 'sale_price' => 100, 'stock' => 0, 'has_variants' => true,
        ]);
        $product->variants()->create(['sku' => 'STK-VARIANT-C', 'options' => ['Size' => 'M'], 'stock' => 5, 'is_active' => true]);

        $path = $this->writeCsv(['STK-PARENT-002,99']);

        try {
            app(ProductCsvService::class)->importStock($path);
            $this->fail('Expected a ValidationException for a variant-bearing product SKU.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('derived from its variants', $exception->errors()['csv'][0]);
        }

        // Untouched — the row was rejected inside the transaction.
        $this->assertSame(5, $product->refresh()->stock);
    }

    public function test_unknown_sku_is_a_row_error(): void
    {
        Storage::fake('local');

        $path = $this->writeCsv(['STK-DOES-NOT-EXIST,10']);

        try {
            app(ProductCsvService::class)->importStock($path);
            $this->fail('Expected a ValidationException for an unknown SKU.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('was not found', $exception->errors()['csv'][0]);
        }
    }

    public function test_negative_or_non_numeric_stock_is_a_row_error(): void
    {
        Storage::fake('local');

        $product = Product::query()->create([
            'name' => 'Bad Stock Product', 'sku' => 'STK-BAD-001',
            'price' => 100, 'sale_price' => 100, 'stock' => 0,
        ]);

        $path = $this->writeCsv(['STK-BAD-001,-3']);

        try {
            app(ProductCsvService::class)->importStock($path);
            $this->fail('Expected a ValidationException for a negative stock value.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('non-negative', $exception->errors()['csv'][0]);
        }

        $this->assertSame(0, $product->refresh()->stock);
    }

    public function test_a_sku_belonging_to_another_company_is_untouched(): void
    {
        Storage::fake('local');

        $companyA = Company::query()->create([
            'name' => 'Stock Co A', 'slug' => 'stock-co-a',
            'invoice_prefix' => 'SCA', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        $companyB = Company::query()->create([
            'name' => 'Stock Co B', 'slug' => 'stock-co-b',
            'invoice_prefix' => 'SCB', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);

        app(CompanyContext::class)->set($companyB);
        $otherCompanyProduct = Product::query()->create([
            'name' => 'Other Company Product', 'sku' => 'STK-SHARED-SKU',
            'price' => 100, 'sale_price' => 100, 'stock' => 0,
        ]);

        app(CompanyContext::class)->set($companyA);

        $path = $this->writeCsv(['STK-SHARED-SKU,50']);

        try {
            app(ProductCsvService::class)->importStock($path);
            $this->fail('Expected a ValidationException — the SKU belongs to a different company.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('was not found', $exception->errors()['csv'][0]);
        }

        $this->assertSame(0, $otherCompanyProduct->refresh()->stock);
    }

    protected function writeCsv(array $rows): string
    {
        $path = Storage::disk('local')->path('product-stock-'.uniqid().'.csv');
        file_put_contents($path, implode(PHP_EOL, array_merge(['sku,stock'], $rows)));

        return $path;
    }
}
