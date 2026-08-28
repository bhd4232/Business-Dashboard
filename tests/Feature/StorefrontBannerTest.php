<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\StorefrontSetting;
use App\Models\StorefrontSlide;
use App\Services\CompanyContext;
use App\Support\StorefrontThemeRegistry;
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

    public function test_multiple_banners_render_as_a_smooth_image_only_carousel(): void
    {
        $company = $this->createPublishedStorefrontCompany('Gadget Store', 'banners-carousel.example.test');

        foreach (['first', 'second'] as $index => $name) {
            StorefrontSlide::query()->create([
                'company_id' => $company->getKey(),
                'image' => "storefront/slides/{$name}.jpg",
                'heading' => "{$name} visible heading",
                'subheading' => "{$name} visible subheading",
                'cta_label' => "{$name} visible CTA",
                'sort_order' => $index,
                'is_active' => true,
            ]);
        }

        $this->get('http://banners-carousel.example.test/')
            ->assertOk()
            ->assertSee('storefront/slides/first.jpg', false)
            ->assertSee('storefront/slides/second.jpg', false)
            ->assertSee('transform 700ms cubic-bezier(0.22, 1, 0.36, 1)', false)
            ->assertSee('Pause banners')
            ->assertSee('first visible heading')
            ->assertSee('first visible subheading')
            ->assertSee('first visible CTA');
    }

    public function test_marketplace_pro_uses_the_same_full_width_image_banner_with_overlay(): void
    {
        $company = $this->createPublishedStorefrontCompany('Marketplace Store', 'marketplace-banner.example.test');

        StorefrontSetting::query()
            ->where('company_id', $company->getKey())
            ->update([
                'storefront_theme' => StorefrontThemeRegistry::MARKETPLACE_PRO,
                'homepage_template' => StorefrontThemeRegistry::MARKETPLACE_HERO,
            ]);

        StorefrontSlide::query()->create([
            'company_id' => $company->getKey(),
            'image' => 'storefront/slides/marketplace.jpg',
            'heading' => 'Overlay this heading',
            'cta_label' => 'Overlay this CTA',
            'is_active' => true,
        ]);

        $this->get('http://marketplace-banner.example.test/')
            ->assertOk()
            ->assertSee('storefront/slides/marketplace.jpg', false)
            ->assertSee('storefront-image-banner', false)
            ->assertSee('Overlay this heading')
            ->assertSee('Overlay this CTA');
    }

    public function test_banner_height_is_driven_by_its_declared_aspect_ratio_not_viewport_height(): void
    {
        // Regression guard: the banner container must size itself from the
        // image's own ratio (matching StorefrontThemeRegistry::BANNER_SPECS),
        // not a viewport-height fraction — a vh-based height ignores the
        // image's actual width:height ratio, so object-cover ends up
        // zooming in and cropping off the banner's own headline/icons on
        // any window whose height doesn't happen to match that fraction.
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('aspect-ratio: 45 / 16;', $css);
        $this->assertStringContainsString('aspect-ratio: 3 / 1;', $css);
        $this->assertStringNotContainsString('100vh / 6', $css);
        $this->assertStringNotContainsString('100vh / 3', $css);
    }

    public function test_banner_fits_to_screen_on_mobile_without_cropping(): void
    {
        // Mobile uses object-fit: contain ("fit to screen") so a slide
        // image is never cropped on a narrow screen, even one uploaded
        // before the ratio-locked image editor existed; desktop keeps
        // object-fit: cover since the wide banner has room to fill
        // edge-to-edge.
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('.storefront-image-banner img {', $css);
        $this->assertStringContainsString('object-fit: contain;', $css);
        $this->assertStringContainsString('object-fit: cover;', $css);
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
