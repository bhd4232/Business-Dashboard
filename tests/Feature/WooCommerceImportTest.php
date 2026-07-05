<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
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
