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
