<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductCsvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_csv_sample_can_be_downloaded(): void
    {
        $user = User::factory()->create([
            'role' => 'inventory_staff',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('products.import.sample'));

        $response->assertOk();
        $response->assertDownload('products-import-sample.csv');
        $this->assertStringContainsString('sku,name,category', $response->streamedContent());
    }

    public function test_product_csv_export_downloads_products(): void
    {
        $user = User::factory()->create([
            'role' => 'inventory_staff',
            'is_active' => true,
        ]);

        $category = Category::query()->create([
            'name' => 'Routers',
            'slug' => 'routers',
        ]);

        Product::query()->create([
            'name' => 'Export Router',
            'sku' => 'EXP-ROUTER-001',
            'price' => 2500,
            'sale_price' => 2500,
            'cost_price' => 1500,
            'stock' => 3,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($user)->get(route('products.export.csv'));

        $response->assertOk();
        $response->assertDownload('products-export.csv');

        $content = $response->streamedContent();

        $this->assertStringContainsString('EXP-ROUTER-001', $content);
        $this->assertStringContainsString('Export Router', $content);
        $this->assertStringContainsString('Routers', $content);
    }

    public function test_product_csv_import_creates_and_updates_products_with_stock(): void
    {
        Storage::fake('local');

        $existing = Product::query()->create([
            'name' => 'Old Cable Name',
            'sku' => 'IMP-CABLE-001',
            'price' => 100,
            'sale_price' => 100,
            'stock' => 0,
        ]);

        $existing->setStockFromProductForm(5);

        $path = Storage::disk('local')->path('products.csv');
        file_put_contents($path, implode(PHP_EOL, [
            implode(',', ProductCsvService::HEADINGS),
            'IMP-ROUTER-001,Import Router,Networking,123456,Mercury,pcs,1400,2200,12,3,0,available,yes,Imported router',
            'IMP-CABLE-001,Updated Cable,Accessories,654321,PowerLine,pcs,80,180,2,5,0,available,no,Updated cable',
        ]));

        $result = app(ProductCsvService::class)->import($path);

        $this->assertSame(['created' => 1, 'updated' => 1], $result);

        $this->assertDatabaseHas('categories', ['name' => 'Networking']);
        $this->assertDatabaseHas('products', [
            'sku' => 'IMP-ROUTER-001',
            'name' => 'Import Router',
            'stock' => 12,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('products', [
            'sku' => 'IMP-CABLE-001',
            'name' => 'Updated Cable',
            'stock' => 2,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => Product::query()->where('sku', 'IMP-CABLE-001')->value('id'),
            'type' => 'adjustment',
            'quantity' => -3,
        ]);
    }

    public function test_product_csv_routes_require_inventory_access(): void
    {
        $user = User::factory()->create([
            'role' => 'accountant',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('products.export.csv'))->assertForbidden();
        $this->actingAs($user)->get(route('products.import.sample'))->assertForbidden();
    }
}
