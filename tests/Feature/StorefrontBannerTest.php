<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\StorefrontSetting;
use App\Models\StorefrontSlide;
use App\Services\CompanyContext;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Homepage banners are hero slides (the old Storefront Settings banner
 * repeaters were merged into StorefrontSlide in v1.20.0).
 */
class StorefrontBannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_product_tagged_slide_links_to_the_products_page(): void
    {
        $company = $this->createPublishedStorefrontCompany('Gadget Store', 'banners.example.test');

        app(CompanyContext::class)->set($company);

        $product = Product::query()->create([
            'name' => 'Fast Charger',
            'sku' => 'FAST-CHARGER-001',
            'slug' => 'fast-charger',
            'price' => 1200,
            'sale_price' => 1100,
            'cost_price' => 700,
            'stock' => 10,
            'unit' => 'pcs',
            'reorder_level' => 2,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);

        StorefrontSlide::query()->create([
            'company_id' => $company->getKey(),
            'image' => 'storefront/slides/one.jpg',
            'product_id' => $product->getKey(),
            'is_active' => true,
        ]);

        $this->get('http://banners.example.test/')
            ->assertOk()
            ->assertSee('storage/storefront/slides/one.jpg', false)
            ->assertSee('href="'.route('storefront.products.show', 'fast-charger').'"', false);
    }

    public function test_cta_url_wins_over_the_product_link(): void
    {
        $company = $this->createPublishedStorefrontCompany('Gadget Store', 'banners-cta.example.test');

        app(CompanyContext::class)->set($company);

        $product = Product::query()->create([
            'name' => 'Power Bank',
            'sku' => 'POWER-BANK-001',
            'slug' => 'power-bank',
            'price' => 2200,
            'cost_price' => 1500,
            'stock' => 5,
            'unit' => 'pcs',
            'reorder_level' => 2,
            'vat_rate' => 0,
            // Hidden from the product grid so the only possible product link
            // on the page would come from the slide itself.
            'is_active' => false,
            'status' => Product::STATUS_AVAILABLE,
        ]);

        StorefrontSlide::query()->create([
            'company_id' => $company->getKey(),
            'image' => 'storefront/slides/cta.jpg',
            'cta_url' => 'https://example.com/offer',
            'product_id' => $product->getKey(),
            'is_active' => true,
        ]);

        $this->get('http://banners-cta.example.test/')
            ->assertOk()
            ->assertSee('href="https://example.com/offer"', false)
            ->assertDontSee('href="'.route('storefront.products.show', 'power-bank').'"', false);
    }

    public function test_untagged_slide_renders_without_a_product_link(): void
    {
        $company = $this->createPublishedStorefrontCompany('Gadget Store', 'banners-plain.example.test');

        StorefrontSlide::query()->create([
            'company_id' => $company->getKey(),
            'image' => 'storefront/slides/plain.jpg',
            'is_active' => true,
        ]);

        $this->get('http://banners-plain.example.test/')
            ->assertOk()
            ->assertSee('storage/storefront/slides/plain.jpg', false);
    }

    public function test_mobile_image_renders_as_a_picture_source(): void
    {
        $company = $this->createPublishedStorefrontCompany('Gadget Store', 'banners-mobile.example.test');

        StorefrontSlide::query()->create([
            'company_id' => $company->getKey(),
            'image' => 'storefront/slides/desktop.jpg',
            'image_mobile' => 'storefront/slides/mobile.jpg',
            'is_active' => true,
        ]);

        $this->get('http://banners-mobile.example.test/')
            ->assertOk()
            ->assertSee('storefront/slides/desktop.jpg', false)
            ->assertSee('storefront/slides/mobile.jpg', false);
    }

    private function createPublishedStorefrontCompany(string $name, string $domain): Company
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString().'-'.str($domain)->slug()->toString(),
            'domain' => $domain,
            'domain_verified' => true,
            'invoice_prefix' => str($name)->substr(0, 3)->upper()->toString(),
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        StorefrontSetting::query()->create([
            'company_id' => $company->getKey(),
            'theme_color' => '#0F766E',
            'whatsapp_number' => '+8801700000000',
            'meta_title' => $name,
            'is_published' => true,
        ]);

        return $company;
    }
}
