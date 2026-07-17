<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\StorefrontSetting;
use App\Services\CompanyContext;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontBannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_product_tagged_banner_links_to_the_products_page(): void
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

        $company->storefrontSetting->update([
            'banner_images' => [
                ['image' => 'storefront/banners/one.jpg', 'product_id' => $product->getKey()],
            ],
        ]);

        $this->get('http://banners.example.test/')
            ->assertOk()
            ->assertSee('storage/storefront/banners/one.jpg', false)
            ->assertSee('href="'.route('storefront.products.show', 'fast-charger').'"', false);
    }

    public function test_untagged_banner_renders_without_a_product_link(): void
    {
        $company = $this->createPublishedStorefrontCompany('Gadget Store', 'banners-plain.example.test');

        $company->storefrontSetting->update([
            'banner_images' => [
                ['image' => 'storefront/banners/plain.jpg', 'product_id' => null],
            ],
        ]);

        $this->get('http://banners-plain.example.test/')
            ->assertOk()
            ->assertSee('storage/storefront/banners/plain.jpg', false);
    }

    public function test_mobile_banners_fall_back_to_desktop_banners_when_empty(): void
    {
        $company = $this->createPublishedStorefrontCompany('Gadget Store', 'banners-mobile.example.test');

        $company->storefrontSetting->update([
            'banner_images' => [
                ['image' => 'storefront/banners/desktop.jpg', 'product_id' => null],
            ],
        ]);

        $this->get('http://banners-mobile.example.test/')
            ->assertOk()
            ->assertSeeInOrder(['storefront/banners/desktop.jpg', 'storefront/banners/desktop.jpg']);
    }

    public function test_legacy_plain_string_banner_entries_still_render(): void
    {
        $company = $this->createPublishedStorefrontCompany('Gadget Store', 'banners-legacy.example.test');

        $company->storefrontSetting->update([
            'banner_images' => ['storefront/banners/legacy.jpg'],
        ]);

        $this->get('http://banners-legacy.example.test/')
            ->assertOk()
            ->assertSee('storage/storefront/banners/legacy.jpg', false);
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
