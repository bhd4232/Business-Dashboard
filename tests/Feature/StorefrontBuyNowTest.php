<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\StorefrontSetting;
use App\Services\CompanyContext;
use App\Services\StorefrontCart;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontBuyNowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_buy_now_adds_to_cart_and_redirects_straight_to_checkout(): void
    {
        $company = $this->createStore('buynow.example.test');

        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('Buy Now Item', 'BUYNOW-001');

        $this->post('http://buynow.example.test/cart/items/'.$product->slug, [
            'quantity' => 2,
            'buy_now' => '1',
        ])->assertRedirect('http://buynow.example.test/checkout');

        $this->assertSame(2, app(StorefrontCart::class)->items($company)->first()['quantity']);
    }

    public function test_add_to_cart_without_buy_now_returns_to_previous_page(): void
    {
        $company = $this->createStore('addonly.example.test');

        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('Add Only Item', 'ADDONLY-001');

        $response = $this->from('http://addonly.example.test/product/'.$product->slug)
            ->post('http://addonly.example.test/cart/items/'.$product->slug, [
                'quantity' => 1,
            ]);

        $response->assertRedirect('http://addonly.example.test/product/'.$product->slug);
    }

    private function createProduct(string $name, string $sku): Product
    {
        return Product::query()->create([
            'name' => $name,
            'sku' => $sku,
            'price' => 500,
            'sale_price' => 500,
            'cost_price' => 300,
            'stock' => 10,
            'unit' => 'pcs',
            'reorder_level' => 2,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);
    }

    private function createStore(string $domain): Company
    {
        $company = Company::query()->create([
            'name' => 'Store '.$domain,
            'slug' => str($domain)->slug()->toString(),
            'domain' => $domain,
            'domain_verified' => true,
            'invoice_prefix' => 'BUY',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        StorefrontSetting::query()->create([
            'company_id' => $company->getKey(),
            'theme_color' => '#0F766E',
            'is_published' => true,
        ]);

        return $company;
    }
}
