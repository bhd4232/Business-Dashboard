<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\StorefrontSetting;
use App\Services\CompanyContext;
use App\Support\StorefrontThemeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontThemeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_current_storefront_is_registered_as_the_built_in_theme(): void
    {
        $setting = new StorefrontSetting;

        $this->assertSame('Built-in Theme', StorefrontThemeRegistry::themeOptions()['builtin']);
        $this->assertSame(StorefrontThemeRegistry::BUILT_IN, $setting->storefrontTheme());
        $this->assertSame(StorefrontThemeRegistry::BUILT_IN_DEFAULT, $setting->homepageTemplate());
        $this->assertSame('storefront.home', $setting->homepageView());
    }

    public function test_invalid_theme_and_template_values_fall_back_safely(): void
    {
        $setting = new StorefrontSetting([
            'storefront_theme' => 'missing-theme',
            'homepage_template' => 'missing-template',
        ]);

        $this->assertSame(StorefrontThemeRegistry::BUILT_IN, $setting->storefrontTheme());
        $this->assertSame(StorefrontThemeRegistry::BUILT_IN_DEFAULT, $setting->homepageTemplate());
        $this->assertSame('storefront.home', $setting->homepageView());
    }

    public function test_marketplace_pro_renders_all_three_homepage_templates(): void
    {
        [$company, $setting] = $this->createMarketplaceStore();

        $templates = [
            StorefrontThemeRegistry::MARKETPLACE_HERO => 'Better value on dependable products for your business',
            StorefrontThemeRegistry::MARKETPLACE_CAMPAIGN => 'Wholesale savings across essential business supplies',
            StorefrontThemeRegistry::MARKETPLACE_COMPACT => 'All available products',
        ];

        foreach ($templates as $template => $expectedCopy) {
            $setting->forceFill(['homepage_template' => $template])->save();

            $this->get('http://marketplace.example.test/')
                ->assertOk()
                ->assertSee('data-storefront-theme="marketplace_pro"', false)
                ->assertSee('data-homepage-template="'.$template.'"', false)
                ->assertSee($expectedCopy)
                ->assertSee('Wholesale Router');
        }
    }

    public function test_marketplace_feature_controls_hide_disabled_sections(): void
    {
        [, $setting] = $this->createMarketplaceStore();

        $setting->forceFill([
            'homepage_template' => StorefrontThemeRegistry::MARKETPLACE_CAMPAIGN,
            'marketplace_categories_enabled' => false,
            'marketplace_deals_enabled' => false,
            'marketplace_bulk_pricing_enabled' => false,
            'marketplace_business_accounts_enabled' => false,
        ])->save();

        $this->get('http://marketplace.example.test/')
            ->assertOk()
            ->assertDontSee('Verified business accounts get flexible payment terms')
            ->assertDontSee('Shop by category')
            ->assertDontSee('Flash deals')
            ->assertDontSee('Bulk pricing benefits')
            ->assertDontSee('Open a business account');
    }

    public function test_marketplace_announcement_bar_is_not_rendered(): void
    {
        [, $setting] = $this->createMarketplaceStore();

        $setting->forceFill([
            'marketplace_announcement_enabled' => true,
            'marketplace_announcement_text' => 'Legacy announcement must stay hidden',
        ])->save();

        $this->get('http://marketplace.example.test/')
            ->assertOk()
            ->assertDontSee('Legacy announcement must stay hidden')
            ->assertDontSee('marketplace-announcement', false);
    }

    public function test_noor_solar_theme_is_registered_with_its_brand_presets(): void
    {
        $this->assertSame('Noor Solar Energy', StorefrontThemeRegistry::themeOptions()[StorefrontThemeRegistry::NOOR_SOLAR]);
        $this->assertSame(
            [StorefrontThemeRegistry::NOOR_SOLAR_ENGINEERED => 'Solar engineered'],
            StorefrontThemeRegistry::templateOptions(StorefrontThemeRegistry::NOOR_SOLAR),
        );
        $this->assertSame('#064C38', StorefrontSetting::themePalettePresetFields('noor_solar')['theme_color']);
        $this->assertSame('sora', StorefrontSetting::typographyPresetFields('noor_solar')['typography_heading_font']);
        $this->assertGreaterThanOrEqual(4.5, StorefrontSetting::colorContrastRatio('#FFFFFF', '#064C38'));
        $this->assertGreaterThanOrEqual(4.5, StorefrontSetting::colorContrastRatio('#043628', '#F5BF17'));
    }

    public function test_noor_solar_theme_renders_erp_products_and_accessible_interactions(): void
    {
        [, , $product] = $this->createNoorSolarStore();

        $this->get('http://noor-solar.example.test/')
            ->assertOk()
            ->assertSee('data-storefront-theme="noor_solar"', false)
            ->assertSee('data-homepage-template="solar_engineered"', false)
            ->assertSee('Solar buying, re-engineered.')
            ->assertSee('Noor N-Type 550W')
            ->assertSee('data-noor-module-lab', false)
            ->assertSee('data-noor-module-view="front"', false)
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('http://noor-solar.example.test/contact', false);

        $this->get('http://noor-solar.example.test/product/'.$product->slug)
            ->assertOk()
            ->assertSee('data-storefront-theme="noor_solar"', false)
            ->assertSee('--storefront-brand: #064C38', false)
            ->assertSee('--storefront-heading-font: &#039;Sora&#039;', false);
    }

    private function createNoorSolarStore(): array
    {
        $company = Company::query()->create([
            'name' => 'Noor Solar Energy',
            'slug' => 'noor-solar-energy',
            'domain' => 'noor-solar.example.test',
            'domain_verified' => true,
            'invoice_prefix' => 'NSE',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        app(CompanyContext::class)->set($company);

        $category = Category::query()->create([
            'name' => 'Solar Modules',
            'slug' => 'solar-modules',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'name' => 'Noor N-Type 550W',
            'sku' => 'NOOR-550-TOPCON',
            'price' => 18500,
            'sale_price' => 17900,
            'cost_price' => 14000,
            'stock' => 120,
            'unit' => 'pcs',
            'reorder_level' => 10,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
            'category_id' => $category->getKey(),
        ]);

        $setting = StorefrontSetting::query()->create([
            'company_id' => $company->getKey(),
            'storefront_theme' => StorefrontThemeRegistry::NOOR_SOLAR,
            'homepage_template' => StorefrontThemeRegistry::NOOR_SOLAR_ENGINEERED,
            'theme_palette_preset' => 'noor_solar',
            'theme_color' => '#064C38',
            'theme_secondary_color' => '#043628',
            'theme_accent_color' => '#F5BF17',
            'theme_background_color' => '#F7F6F2',
            'theme_surface_color' => '#FFFFFF',
            'theme_text_color' => '#043628',
            'theme_muted_text_color' => '#52635D',
            'theme_border_color' => '#E5E3DA',
            'theme_dark_background_color' => '#031E17',
            'theme_dark_surface_color' => '#073B2C',
            'theme_dark_text_color' => '#F7F6F2',
            'theme_dark_muted_text_color' => '#B9C8C1',
            'theme_dark_border_color' => '#28604E',
            'typography_preset' => 'noor_solar',
            'typography_heading_font' => 'sora',
            'typography_body_font' => 'inter',
            'is_published' => true,
        ]);

        return [$company, $setting, $product];
    }

    private function createMarketplaceStore(): array
    {
        $company = Company::query()->create([
            'name' => 'Marketplace Store',
            'slug' => 'marketplace-store',
            'domain' => 'marketplace.example.test',
            'domain_verified' => true,
            'invoice_prefix' => 'MKT',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        app(CompanyContext::class)->set($company);

        $category = Category::query()->create([
            'name' => 'Networking',
            'slug' => 'networking',
            'is_active' => true,
        ]);

        Product::query()->create([
            'name' => 'Wholesale Router',
            'sku' => 'WHOLESALE-ROUTER-001',
            'price' => 2500,
            'sale_price' => 2200,
            'cost_price' => 1500,
            'stock' => 20,
            'unit' => 'pcs',
            'reorder_level' => 2,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
            'category_id' => $category->getKey(),
        ]);

        $setting = StorefrontSetting::query()->create([
            'company_id' => $company->getKey(),
            'storefront_theme' => StorefrontThemeRegistry::MARKETPLACE_PRO,
            'homepage_template' => StorefrontThemeRegistry::MARKETPLACE_HERO,
            'theme_color' => '#0D9488',
            'theme_secondary_color' => '#0F2A43',
            'theme_accent_color' => '#FF6A00',
            'is_published' => true,
        ]);

        return [$company, $setting];
    }
}
