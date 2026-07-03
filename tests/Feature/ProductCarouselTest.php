<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCarousel;
use App\Models\StorefrontSetting;
use App\Services\CompanyContext;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCarouselTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_active_carousel_with_products_shows_on_homepage(): void
    {
        $company = $this->createPublishedStorefrontCompany('Gadget Store', 'carousel.example.test');

        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('Carousel Charger', 'CAR-CHARGER-001');

        $carousel = ProductCarousel::query()->create([
            'title' => 'Best Sellers',
            'subtitle' => 'Most loved this month',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $carousel->products()->attach($product->getKey(), ['sort_order' => 0]);

        $this->get('http://carousel.example.test/')
            ->assertOk()
            ->assertSee('Best Sellers')
            ->assertSee('Most loved this month')
            ->assertSee('Carousel Charger');
    }

    public function test_inactive_or_empty_carousels_are_hidden(): void
    {
        $company = $this->createPublishedStorefrontCompany('Gadget Store', 'carousel-hidden.example.test');

        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('Hidden Charger', 'HID-CHARGER-001');

        $inactive = ProductCarousel::query()->create([
            'title' => 'Inactive Section',
            'is_active' => false,
        ]);
        $inactive->products()->attach($product->getKey());

        ProductCarousel::query()->create([
            'title' => 'Empty Section',
            'is_active' => true,
        ]);

        $unavailable = $this->createProduct('Unavailable Item', 'UNAV-ITEM-001', available: false);
        $onlyUnavailable = ProductCarousel::query()->create([
            'title' => 'Sold Out Section',
            'is_active' => true,
        ]);
        $onlyUnavailable->products()->attach($unavailable->getKey());

        $this->get('http://carousel-hidden.example.test/')
            ->assertOk()
            ->assertDontSee('Inactive Section')
            ->assertDontSee('Empty Section')
            ->assertDontSee('Sold Out Section');
    }

    public function test_carousels_are_company_isolated(): void
    {
        $gadget = $this->createPublishedStorefrontCompany('Gadget Store', 'carousel-gadget.example.test');
        $gift = $this->createPublishedStorefrontCompany('Gift Store', 'carousel-gift.example.test');

        app(CompanyContext::class)->set($gift);
        $giftProduct = $this->createProduct('Gift Box Deluxe', 'GIFT-BOX-DLX-001');
        $giftCarousel = ProductCarousel::query()->create([
            'title' => 'Gift Picks',
            'is_active' => true,
        ]);
        $giftCarousel->products()->attach($giftProduct->getKey());

        app(CompanyContext::class)->set($gadget);

        $this->get('http://carousel-gadget.example.test/')
            ->assertOk()
            ->assertDontSee('Gift Picks')
            ->assertDontSee('Gift Box Deluxe');

        $this->get('http://carousel-gift.example.test/')
            ->assertOk()
            ->assertSee('Gift Picks')
            ->assertSee('Gift Box Deluxe');
    }

    private function createProduct(string $name, string $sku, bool $available = true): Product
    {
        return Product::query()->create([
            'name' => $name,
            'sku' => $sku,
            'price' => 1000,
            'sale_price' => 900,
            'cost_price' => 500,
            'stock' => 10,
            'unit' => 'pcs',
            'reorder_level' => 2,
            'vat_rate' => 0,
            'is_active' => $available,
            'status' => $available ? Product::STATUS_AVAILABLE : Product::STATUS_COMING_SOON,
        ]);
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
