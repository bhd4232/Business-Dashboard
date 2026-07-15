<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\StorefrontSetting;
use App\Models\StorefrontSlide;
use App\Services\CompanyContext;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontSlideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_active_slide_shows_on_homepage(): void
    {
        $company = $this->createPublishedStorefrontCompany('Gadget Store', 'slides.example.test');

        app(CompanyContext::class)->set($company);

        StorefrontSlide::query()->create([
            'image' => 'storefront/slides/hero.jpg',
            'heading' => 'Big Summer Sale',
            'subheading' => 'Up to 50% off electronics',
            'cta_label' => 'Shop now',
            'is_active' => true,
        ]);

        $this->get('http://slides.example.test/')
            ->assertOk()
            ->assertSee('Big Summer Sale')
            ->assertSee('Up to 50% off electronics')
            ->assertSee('Shop now');
    }

    public function test_inactive_and_out_of_window_slides_are_hidden(): void
    {
        $company = $this->createPublishedStorefrontCompany('Gadget Store', 'slides-hidden.example.test');

        app(CompanyContext::class)->set($company);

        StorefrontSlide::query()->create([
            'image' => 'storefront/slides/inactive.jpg',
            'heading' => 'Inactive Slide',
            'is_active' => false,
        ]);

        StorefrontSlide::query()->create([
            'image' => 'storefront/slides/future.jpg',
            'heading' => 'Future Slide',
            'is_active' => true,
            'starts_at' => now()->addDay(),
        ]);

        StorefrontSlide::query()->create([
            'image' => 'storefront/slides/expired.jpg',
            'heading' => 'Expired Slide',
            'is_active' => true,
            'ends_at' => now()->subDay(),
        ]);

        $this->get('http://slides-hidden.example.test/')
            ->assertOk()
            ->assertDontSee('Inactive Slide')
            ->assertDontSee('Future Slide')
            ->assertDontSee('Expired Slide');
    }

    public function test_slides_are_company_isolated(): void
    {
        $gadget = $this->createPublishedStorefrontCompany('Gadget Store', 'slides-gadget.example.test');
        $gift = $this->createPublishedStorefrontCompany('Gift Store', 'slides-gift.example.test');

        app(CompanyContext::class)->set($gift);
        StorefrontSlide::query()->create([
            'image' => 'storefront/slides/gift.jpg',
            'heading' => 'Gift Store Slide',
            'is_active' => true,
        ]);

        app(CompanyContext::class)->set($gadget);

        $this->assertSame(0, StorefrontSlide::query()->count());

        $this->get('http://slides-gadget.example.test/')
            ->assertOk()
            ->assertDontSee('Gift Store Slide');

        $this->get('http://slides-gift.example.test/')
            ->assertOk()
            ->assertSee('Gift Store Slide');
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
