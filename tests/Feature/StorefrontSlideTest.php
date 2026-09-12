<?php

namespace Tests\Feature;

use App\Filament\Resources\StorefrontSlides\Pages\CreateStorefrontSlide;
use App\Filament\Resources\StorefrontSlides\Pages\ListStorefrontSlides;
use App\Models\Company;
use App\Models\StorefrontSetting;
use App\Models\StorefrontSlide;
use App\Models\User;
use App\Services\CompanyContext;
use Filament\Forms\Components\Toggle;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    public function test_active_slide_shows_as_an_image_banner_with_no_overlay_when_fields_are_empty(): void
    {
        $company = $this->createPublishedStorefrontCompany('Gadget Store', 'slides.example.test');

        app(CompanyContext::class)->set($company);

        StorefrontSlide::query()->create([
            'image' => 'storefront/slides/hero.jpg',
            'is_active' => true,
        ]);

        $this->get('http://slides.example.test/')
            ->assertOk()
            ->assertSee('storefront/slides/hero.jpg', false)
            ->assertSee('storefront-image-banner', false)
            ->assertDontSee('Big Summer Sale')
            ->assertDontSee('Up to 50% off electronics')
            ->assertDontSee('Shop now');
    }

    public function test_slide_heading_subheading_and_cta_render_as_an_overlay_when_filled(): void
    {
        $company = $this->createPublishedStorefrontCompany('Gadget Store', 'slides-overlay.example.test');

        app(CompanyContext::class)->set($company);

        StorefrontSlide::query()->create([
            'image' => 'storefront/slides/hero.jpg',
            'heading' => 'Big Summer Sale',
            'subheading' => 'Up to 50% off electronics',
            'cta_label' => 'Shop now',
            'is_active' => true,
        ]);

        $this->get('http://slides-overlay.example.test/')
            ->assertOk()
            ->assertSee('storefront/slides/hero.jpg', false)
            ->assertSee('Big Summer Sale')
            ->assertSee('Up to 50% off electronics')
            ->assertSee('Shop now');
    }

    public function test_fit_to_frame_slide_is_marked_so_its_image_is_never_cropped(): void
    {
        $company = $this->createPublishedStorefrontCompany('Gadget Store', 'slides-fit.example.test');

        app(CompanyContext::class)->set($company);

        StorefrontSlide::query()->create([
            'image' => 'storefront/slides/off-ratio.jpg',
            'fit_to_frame' => true,
            'is_active' => true,
        ]);

        $this->get('http://slides-fit.example.test/')
            ->assertOk()
            ->assertSee('storefront/slides/off-ratio.jpg', false)
            ->assertSee('storefront-image-banner-fit', false);
    }

    public function test_a_standard_slide_is_not_marked_fit_to_frame(): void
    {
        $company = $this->createPublishedStorefrontCompany('Gadget Store', 'slides-nofit.example.test');

        app(CompanyContext::class)->set($company);

        StorefrontSlide::query()->create([
            'image' => 'storefront/slides/exact-ratio.jpg',
            'is_active' => true,
        ]);

        $this->get('http://slides-nofit.example.test/')
            ->assertOk()
            ->assertSee('storefront/slides/exact-ratio.jpg', false)
            ->assertDontSee('storefront-image-banner-fit', false);
    }

    public function test_hero_slide_form_exposes_the_fit_to_frame_toggle(): void
    {
        $user = User::factory()->create();
        $company = $user->defaultCompany();
        app(CompanyContext::class)->set($company);
        $this->actingAs($user);

        $toggle = collect(
            Livewire::test(CreateStorefrontSlide::class)
                ->instance()
                ->getSchema('form')
                ?->getFlatComponents(withHidden: true)
        )->first(fn ($component): bool => $component instanceof Toggle && $component->getName() === 'fit_to_frame');

        $this->assertInstanceOf(Toggle::class, $toggle);
        $this->assertFalse($toggle->getDefaultState());
    }

    public function test_hero_slides_page_pagination_toggles_persist_per_display(): void
    {
        $user = User::factory()->create();
        $company = $user->defaultCompany();
        app(CompanyContext::class)->set($company);
        $this->actingAs($user);

        $setting = StorefrontSetting::withoutGlobalScopes()->firstOrCreate(['company_id' => $company->getKey()]);

        Livewire::test(ListStorefrontSlides::class)
            ->assertActionExists('paginationDisplay')
            ->callAction('paginationDisplay', [
                'banner_pagination_desktop' => false,
                'banner_pagination_mobile' => true,
            ])
            ->assertHasNoActionErrors();

        $setting->refresh();
        $this->assertFalse($setting->showsBannerPagination('desktop'));
        $this->assertTrue($setting->showsBannerPagination('mobile'));
    }

    public function test_hero_slides_page_pagination_form_prefills_the_current_values(): void
    {
        $user = User::factory()->create();
        $company = $user->defaultCompany();
        app(CompanyContext::class)->set($company);
        $this->actingAs($user);

        $setting = StorefrontSetting::withoutGlobalScopes()->firstOrCreate(['company_id' => $company->getKey()]);
        $setting->update(['banner_pagination_desktop' => false, 'banner_pagination_mobile' => false]);

        // Mount and submit without touching anything: if the form prefilled
        // from the row (not its own ->default(true)), both stay off.
        Livewire::test(ListStorefrontSlides::class)
            ->mountAction('paginationDisplay')
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $setting->refresh();
        $this->assertFalse($setting->showsBannerPagination('desktop'));
        $this->assertFalse($setting->showsBannerPagination('mobile'));
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
            ->assertSee('storefront/slides/gift.jpg', false)
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
