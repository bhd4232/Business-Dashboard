<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StorefrontSetting;
use App\Services\WooCommerceImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class WooCommerceImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_creates_and_updates_products_by_sku(): void
    {
        $company = $this->createCompanyWithWooCredentials('woo-import');

        Http::fake([
            'old-shop.example.test/wp-json/wc/v3/products*' => Http::response([
                [
                    'name' => 'Imported Fan',
                    'sku' => 'WOO-FAN-01',
                    'slug' => 'imported-fan',
                    'regular_price' => '1500',
                    'sale_price' => '1350',
                    'short_description' => '<p>A nice fan.</p>',
                    'categories' => [['name' => 'Fans']],
                    'images' => [],
                ],
                [
                    'name' => 'Imported Light',
                    'sku' => 'WOO-LIGHT-01',
                    'slug' => 'imported-light',
                    'regular_price' => '400',
                    'sale_price' => '',
                    'categories' => [],
                    'images' => [],
                ],
            ]),
        ]);

        $result = app(WooCommerceImportService::class)->importProducts($company, downloadImages: false);

        $this->assertSame(['created' => 2, 'updated' => 0, 'skipped' => 0], $result);

        $fan = Product::withoutGlobalScopes()->where('sku', 'WOO-FAN-01')->first();
        $this->assertNotNull($fan);
        $this->assertSame($company->getKey(), $fan->company_id);
        $this->assertSame(1350.0, (float) $fan->sale_price);
        $this->assertSame(1500.0, (float) $fan->price);
        $this->assertSame(0, (int) $fan->stock);
        $this->assertSame('A nice fan.', $fan->description);
        $this->assertNotNull(Category::withoutGlobalScopes()->where('name', 'Fans')->where('company_id', $company->getKey())->first());

        // Re-running updates instead of duplicating.
        $result = app(WooCommerceImportService::class)->importProducts($company, downloadImages: false);
        $this->assertSame(['created' => 0, 'updated' => 2, 'skipped' => 0], $result);
        $this->assertSame(2, Product::withoutGlobalScopes()->where('company_id', $company->getKey())->count());
    }

    public function test_variable_product_imports_its_variations(): void
    {
        $company = $this->createCompanyWithWooCredentials('woo-variable');

        $productPayload = [
            'id' => 77,
            'type' => 'variable',
            'name' => 'Vari Hoodie',
            'sku' => 'WOO-HOODIE',
            'slug' => 'vari-hoodie',
            'regular_price' => '',
            'price' => '900',
            'description' => '<p>Full hoodie description with all the details.</p>',
            'short_description' => '<p>Short.</p>',
            'attributes' => [
                ['name' => 'Size', 'options' => ['M', 'L'], 'variation' => true],
                ['name' => 'Brand', 'options' => ['ZamZam'], 'variation' => false],
            ],
            'categories' => [['name' => 'Clothing']],
            'images' => [],
        ];

        $variations = [
            [
                'id' => 771,
                'sku' => 'WOO-HOODIE-M',
                'regular_price' => '900',
                'sale_price' => '850',
                'status' => 'publish',
                'menu_order' => 0,
                'attributes' => [['name' => 'Size', 'option' => 'M']],
                'image' => null,
            ],
            [
                'id' => 772,
                'sku' => '',
                'regular_price' => '950',
                'sale_price' => '',
                'status' => 'publish',
                'menu_order' => 1,
                'attributes' => [['name' => 'Size', 'option' => 'L']],
                'image' => null,
            ],
        ];

        Http::fake([
            'old-shop.example.test/wp-json/wc/v3/products/77/variations*' => Http::response($variations),
            'old-shop.example.test/wp-json/wc/v3/products*' => Http::response([$productPayload]),
        ]);

        $result = app(WooCommerceImportService::class)->importProducts($company, downloadImages: false);
        $this->assertSame(['created' => 1, 'updated' => 0, 'skipped' => 0], $result);

        $product = Product::withoutGlobalScopes()->where('sku', 'WOO-HOODIE')->first();
        $this->assertNotNull($product);
        $this->assertTrue((bool) $product->has_variants);
        $this->assertSame(['Size' => ['M', 'L']], $product->variant_attributes);
        $this->assertSame('Full hoodie description with all the details.', $product->description);
        $this->assertSame('ZamZam', $product->brand);

        $variants = ProductVariant::withoutGlobalScopes()->where('product_id', $product->getKey())->orderBy('sort_order')->get();
        $this->assertCount(2, $variants);
        $this->assertSame('WOO-HOODIE-M', $variants[0]->sku);
        $this->assertSame(['Size' => 'M'], $variants[0]->options);
        $this->assertSame(850.0, (float) $variants[0]->sale_price);
        $this->assertNull($variants[1]->sku);
        $this->assertSame(['Size' => 'L'], $variants[1]->options);
        $this->assertSame(950.0, (float) $variants[1]->sale_price);
        $this->assertSame($company->getKey(), $variants[0]->company_id);

        // Re-sync updates the same variants instead of duplicating them.
        $result = app(WooCommerceImportService::class)->importProducts($company, downloadImages: false);
        $this->assertSame(['created' => 0, 'updated' => 1, 'skipped' => 0], $result);
        $this->assertSame(2, ProductVariant::withoutGlobalScopes()->where('product_id', $product->getKey())->count());
    }

    public function test_import_fails_without_configured_credentials(): void
    {
        $company = Company::query()->create([
            'name' => 'No Woo Store',
            'slug' => 'no-woo-store',
            'invoice_prefix' => 'NWS',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        StorefrontSetting::query()->create([
            'company_id' => $company->getKey(),
            'theme_color' => '#0F766E',
            'is_published' => true,
        ]);

        $this->expectException(RuntimeException::class);

        app(WooCommerceImportService::class)->importProducts($company);
    }

    public function test_command_imports_products(): void
    {
        $company = $this->createCompanyWithWooCredentials('woo-cmd');

        Http::fake([
            'old-shop.example.test/wp-json/wc/v3/products*' => Http::response([
                [
                    'name' => 'Command Product',
                    'sku' => 'WOO-CMD-01',
                    'slug' => 'command-product',
                    'regular_price' => '999',
                    'categories' => [],
                    'images' => [],
                ],
            ]),
        ]);

        $this->artisan('woocommerce:import-products', ['company' => $company->slug, '--no-images' => true])
            ->expectsOutputToContain('Created: 1')
            ->assertSuccessful();
    }

    private function createCompanyWithWooCredentials(string $slug): Company
    {
        $company = Company::query()->create([
            'name' => 'Woo Store '.$slug,
            'slug' => $slug,
            'invoice_prefix' => 'WOO',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        StorefrontSetting::query()->create([
            'company_id' => $company->getKey(),
            'theme_color' => '#0F766E',
            'is_published' => true,
            'woocommerce_base_url' => 'https://old-shop.example.test',
            'woocommerce_credentials' => [
                'consumer_key' => 'ck_test',
                'consumer_secret' => 'cs_test',
            ],
        ]);

        return $company;
    }
}
