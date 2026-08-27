<?php

namespace Tests\Feature;

use App\Mail\StorefrontLoginOtp;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\StorefrontPage;
use App\Models\StorefrontSetting;
use App\Services\CompanyContext;
use App\Services\StorefrontNotificationService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Covers the 08_STOREFRONT_UX_FIXES_PLAN.md items that carry real branching
 * logic (footer builder, reseller toggle, Google Maps override, header
 * category icons, per-company SMTP, rich product descriptions) rather than
 * pure CSS/layout changes, which are checked visually instead.
 */
class StorefrontUxFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_footer_falls_back_to_default_blocks_when_none_are_configured(): void
    {
        $company = $this->createPublishedStorefrontCompany('Footer Default Store', 'footer-default.example.test');

        $this->get('http://footer-default.example.test/')
            ->assertOk()
            ->assertSee('Footer Default Store')
            ->assertSee('Contact us')
            ->assertSee((string) now()->year);
    }

    public function test_admin_configured_footer_blocks_control_what_renders(): void
    {
        $company = $this->createPublishedStorefrontCompany('Footer Custom Store', 'footer-custom.example.test');

        StorefrontPage::query()->create([
            'company_id' => $company->getKey(),
            'title' => 'Privacy Policy',
            'slug' => 'privacy-policy',
            'content' => 'Policy text.',
            'is_published' => true,
        ]);

        StorefrontSetting::query()->where('company_id', $company->getKey())->update([
            'footer_blocks' => [
                ['type' => 'brand_about', 'data' => ['text' => 'Custom about text for this store.']],
                ['type' => 'bottom_bar', 'data' => [
                    'copyright_text' => '{company_name} — all rights reserved {year}',
                    'legal_links' => [
                        ['label' => 'Privacy Policy', 'page_id' => StorefrontPage::query()->where('slug', 'privacy-policy')->value('id')],
                    ],
                ]],
            ],
        ]);

        $this->get('http://footer-custom.example.test/')
            ->assertOk()
            ->assertSee('Custom about text for this store.')
            ->assertSee('Footer Custom Store — all rights reserved '.now()->year)
            ->assertSee('Privacy Policy')
            // quick_links/contact_info blocks were deliberately left out —
            // their content must not render.
            ->assertDontSee('Product complaint');
    }

    public function test_social_links_block_only_shows_configured_platforms(): void
    {
        $company = $this->createPublishedStorefrontCompany('Footer Social Store', 'footer-social.example.test');

        StorefrontSetting::query()->where('company_id', $company->getKey())->update([
            'social_links' => [
                ['platform' => 'facebook', 'url' => 'https://facebook.com/footersocialstore'],
            ],
            'footer_blocks' => [
                ['type' => 'social_links', 'data' => []],
            ],
        ]);

        $this->get('http://footer-social.example.test/')
            ->assertOk()
            ->assertSee('https://facebook.com/footersocialstore', false);
    }

    public function test_reseller_toggle_hides_links_from_header_and_footer(): void
    {
        $company = $this->createPublishedStorefrontCompany('Reseller Off Store', 'reseller-off.example.test');

        StorefrontSetting::query()->where('company_id', $company->getKey())->update([
            'reseller_program_enabled' => false,
        ]);

        $response = $this->get('http://reseller-off.example.test/')->assertOk();
        $response->assertDontSee('Become a reseller');

        // The route itself must still work — only the links are hidden.
        $this->get('http://reseller-off.example.test/reseller')->assertOk();
    }

    public function test_google_maps_url_override_wins_over_the_auto_generated_link(): void
    {
        $company = $this->createPublishedStorefrontCompany('Maps Store', 'maps.example.test');
        $company->update([
            'address' => 'Plot 1, Road 2, Dhaka',
            'google_maps_url' => 'https://maps.app.goo.gl/exact-pin-location',
        ]);

        $this->get('http://maps.example.test/contact')
            ->assertOk()
            ->assertSee('https://maps.app.goo.gl/exact-pin-location', false)
            ->assertDontSee('google.com/maps/search', false);
    }

    public function test_google_maps_falls_back_to_auto_generated_link_when_empty(): void
    {
        $company = $this->createPublishedStorefrontCompany('Maps Fallback Store', 'maps-fallback.example.test');
        $company->update(['address' => 'Plot 1, Road 2, Dhaka']);

        $this->get('http://maps-fallback.example.test/contact')
            ->assertOk()
            ->assertSee('google.com/maps/search', false);
    }

    public function test_header_menu_shows_the_categorys_own_icon(): void
    {
        $company = $this->createPublishedStorefrontCompany('Icon Menu Store', 'icon-menu.example.test');

        app(CompanyContext::class)->set($company);

        $category = Category::query()->create([
            'company_id' => $company->getKey(),
            'name' => 'Electronics',
            'slug' => 'electronics',
            'icon' => 'electronics',
            'is_active' => true,
        ]);

        StorefrontSetting::query()->where('company_id', $company->getKey())->update([
            'header_menu' => [
                ['label' => 'Electronics', 'type' => 'category', 'category_id' => $category->getKey()],
            ],
        ]);

        $this->get('http://icon-menu.example.test/')
            ->assertOk()
            ->assertSee('heroicon-o-cpu-chip', false);
    }

    public function test_product_description_renders_rich_html_and_is_escaped_of_scripts(): void
    {
        $company = $this->createPublishedStorefrontCompany('Rich Description Store', 'rich-description.example.test');

        app(CompanyContext::class)->set($company);

        $product = Product::query()->create([
            'name' => 'Rich Text Product',
            'slug' => 'rich-text-product',
            'sku' => 'RTP-1',
            'price' => 500,
            'stock' => 10,
            'unit' => 'pcs',
            'reorder_level' => 2,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
            'description' => '<h2>Key features</h2><p>Built with <strong>quality</strong> parts.</p><script>alert(1)</script>',
        ]);

        $this->get('http://rich-description.example.test/product/'.$product->slug)
            ->assertOk()
            ->assertSee('Key features')
            ->assertSee('quality')
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_mail_configured_reflects_whether_smtp_credentials_are_present(): void
    {
        $company = $this->createPublishedStorefrontCompany('SMTP Store', 'smtp.example.test');
        $setting = StorefrontSetting::query()->where('company_id', $company->getKey())->firstOrFail();
        $service = app(StorefrontNotificationService::class);

        $this->assertFalse($service->mailConfigured($setting));

        $setting->update([
            'notification_credentials' => [
                'mail_host' => 'smtp.example.test',
                'mail_username' => 'store@example.test',
            ],
        ]);

        $this->assertTrue($service->mailConfigured($setting->fresh()));
    }

    public function test_login_otp_email_uses_the_companys_own_smtp_settings_when_configured(): void
    {
        $company = $this->createPublishedStorefrontCompany('SMTP OTP Store', 'smtp-otp.example.test');
        $setting = StorefrontSetting::query()->where('company_id', $company->getKey())->firstOrFail();
        $setting->update([
            'notification_credentials' => [
                'mail_host' => 'smtp.mailtrap.test',
                'mail_port' => 2525,
                'mail_username' => 'store-user',
                'mail_password' => 'secret',
                'mail_from_address' => 'orders@smtpotp.test',
                'mail_from_name' => 'SMTP OTP Store',
            ],
        ]);

        Mail::fake();

        $sent = app(StorefrontNotificationService::class)->sendLoginOtpEmail($setting->fresh(), 'buyer@example.test', $company->name, '123456');

        $this->assertTrue($sent);
        Mail::assertSent(StorefrontLoginOtp::class, fn (StorefrontLoginOtp $mail): bool => $mail->hasTo('buyer@example.test'));
        $this->assertSame('smtp.mailtrap.test', Config::get('mail.mailers.storefront_dynamic.host'));
        $this->assertSame('orders@smtpotp.test', Config::get('mail.from.address'));
    }

    private function createPublishedStorefrontCompany(string $name, string $domain, array $settingOverrides = []): Company
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

        StorefrontSetting::query()->create(array_merge([
            'company_id' => $company->getKey(),
            'theme_color' => '#0F766E',
            'whatsapp_number' => '+8801700000000',
            'meta_title' => $name,
            'is_published' => true,
        ], $settingOverrides));

        return $company;
    }
}
