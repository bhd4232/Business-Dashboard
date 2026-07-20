<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\CompanyFaq;
use App\Models\StorefrontPage;
use App\Models\StorefrontSetting;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin-managed header/footer navigation menus (Storefront Settings →
 * Navigation Menus, added in v1.20.0).
 */
class StorefrontMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_header_menu_items_render_with_resolved_urls(): void
    {
        $company = $this->createPublishedStorefrontCompany('Menu Store', 'menu.example.test');

        $category = Category::query()->create([
            'company_id' => $company->getKey(),
            'name' => 'Chargers',
            'slug' => 'chargers',
            'is_active' => true,
        ]);

        $page = StorefrontPage::query()->create([
            'company_id' => $company->getKey(),
            'title' => 'About Us',
            'slug' => 'about-us',
            'content' => 'About the store.',
            'is_published' => true,
        ]);

        $company->storefrontSetting->update([
            'header_menu' => [
                ['label' => 'All Products', 'type' => 'shop'],
                ['label' => 'Chargers', 'type' => 'category', 'category_id' => $category->getKey()],
                ['label' => 'About', 'type' => 'page', 'page_id' => $page->getKey()],
                ['label' => 'Blog', 'type' => 'custom', 'url' => 'https://blog.example.com', 'new_tab' => true],
            ],
        ]);

        $this->get('http://menu.example.test/')
            ->assertOk()
            ->assertSee('All Products')
            ->assertSee('href="'.route('storefront.products.index').'"', false)
            ->assertSee('href="'.route('storefront.categories.show', 'chargers').'"', false)
            ->assertSee('href="'.route('storefront.pages.show', 'about-us').'"', false)
            ->assertSee('href="https://blog.example.com"', false)
            ->assertSee('target="_blank"', false)
            // Custom menu replaces the default header links.
            ->assertDontSee('Shop all');
    }

    public function test_footer_menu_replaces_the_automatic_pages_list(): void
    {
        $company = $this->createPublishedStorefrontCompany('Menu Store', 'menu-footer.example.test');

        StorefrontPage::query()->create([
            'company_id' => $company->getKey(),
            'title' => 'Hidden Auto Page',
            'slug' => 'hidden-auto-page',
            'content' => 'x',
            'is_published' => true,
        ]);

        $company->storefrontSetting->update([
            'footer_menu' => [
                ['label' => 'Order Tracking', 'type' => 'track'],
                ['label' => 'Reseller Program', 'type' => 'reseller'],
            ],
        ]);

        $this->get('http://menu-footer.example.test/')
            ->assertOk()
            ->assertSee('Quick links')
            ->assertSee('Order Tracking')
            ->assertSee('Reseller Program')
            ->assertDontSee('Hidden Auto Page');
    }

    public function test_broken_menu_items_are_skipped_and_defaults_show_without_a_menu(): void
    {
        $company = $this->createPublishedStorefrontCompany('Menu Store', 'menu-default.example.test');

        // A menu whose every item is unresolvable behaves like no menu at all.
        $company->storefrontSetting->update([
            'header_menu' => [
                ['label' => 'Ghost Category', 'type' => 'category', 'category_id' => 999999],
                ['label' => '', 'type' => 'shop'],
            ],
        ]);

        $this->get('http://menu-default.example.test/')
            ->assertOk()
            ->assertDontSee('Ghost Category')
            ->assertSee('Shop all')
            ->assertSee('Track order');
    }

    public function test_storefront_shell_exposes_accessible_navigation_theme_and_search_controls(): void
    {
        $company = $this->createPublishedStorefrontCompany('Accessible Store', 'accessible-menu.example.test');

        $company->storefrontSetting->update([
            'customer_accounts_enabled' => false,
            'theme_color' => '#F7F7F7',
        ]);

        $this->withSession(['storefront_status' => 'Your changes were saved.'])
            ->get('http://accessible-menu.example.test/')
            ->assertOk()
            ->assertSee('aria-controls="storefront-mobile-menu"', false)
            ->assertSee('id="storefront-mobile-menu"', false)
            ->assertSee('data-close-label="Close menu"', false)
            ->assertSee('data-light-label="Switch to light mode"', false)
            ->assertSee('placeholder="Search products…"', false)
            ->assertSee('autocomplete="off"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('role="status"', false)
            ->assertSee('--storefront-brand-contrast: #000000;', false)
            ->assertDontSee('Find my orders');

        $this->get('http://accessible-menu.example.test/products')
            ->assertOk()
            ->assertSee('storefront-catalog-grid grid grid-cols-2', false);
    }

    public function test_contact_faq_uses_an_accessible_accordion_relationship(): void
    {
        $company = $this->createPublishedStorefrontCompany('FAQ Store', 'faq-menu.example.test');

        $faq = CompanyFaq::query()->create([
            'company_id' => $company->getKey(),
            'question' => 'How long does delivery take?',
            'answer' => 'Delivery usually takes 2 to 4 business days.',
            'is_active' => true,
        ]);

        $this->get('http://faq-menu.example.test/contact')
            ->assertOk()
            ->assertSee('id="faq-'.$faq->getKey().'-trigger"', false)
            ->assertSee('aria-controls="faq-'.$faq->getKey().'-panel"', false)
            ->assertSee('id="faq-'.$faq->getKey().'-panel"', false)
            ->assertSee('role="region"', false)
            ->assertSee('aria-labelledby="faq-'.$faq->getKey().'-trigger"', false)
            ->assertSee(':aria-expanded="(open === 0).toString()"', false);
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
