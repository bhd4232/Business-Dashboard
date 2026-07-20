<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyFaq;
use App\Models\StorefrontPage;
use App\Models\StorefrontSetting;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contact Us page (MoveOn-style reference design) and the redesigned
 * professional article template for admin-authored pages (About, Terms,
 * Privacy, Return & Refund, Advance Payment) — added in v1.20.0.
 */
class StorefrontContentPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_contact_page_renders_method_cards_location_and_faqs(): void
    {
        $company = $this->createPublishedStorefrontCompany('Contact Store', 'contact.example.test', [
            'whatsapp_number' => '+8801700000000',
            'phone_number' => '+8809666786000',
            'contact_email' => 'support@contactstore.test',
            'contact_hours' => 'Saturday-Friday, 10:00 AM to 10:00 PM',
        ]);

        $company->update(['address' => 'Plot 1020, Road 9, Mirpur DOHS, Dhaka-1216']);

        CompanyFaq::query()->create([
            'company_id' => $company->getKey(),
            'question' => 'Can I track my order?',
            'answer' => 'Yes, use the tracking link in your confirmation message.',
            'is_active' => true,
        ]);

        CompanyFaq::query()->create([
            'company_id' => $company->getKey(),
            'question' => 'Hidden inactive question',
            'answer' => 'Should not appear.',
            'is_active' => false,
        ]);

        $this->get('http://contact.example.test/contact')
            ->assertOk()
            ->assertSee('Connect with Us')
            ->assertSee('Email Us')
            ->assertSee('support@contactstore.test')
            ->assertSee('Chat on WhatsApp')
            ->assertSee('Call Us')
            ->assertSee('Saturday-Friday, 10:00 AM to 10:00 PM')
            ->assertSee('Help Center')
            ->assertSee('Our Location')
            ->assertSee('Plot 1020, Road 9, Mirpur DOHS, Dhaka-1216')
            ->assertSee('Find on Map')
            ->assertSee('Frequently Asked Questions')
            ->assertSee('Can I track my order?')
            ->assertDontSee('Hidden inactive question')
            ->assertSee('Still Have Questions?');
    }

    public function test_contact_page_hides_sections_when_no_data_is_configured(): void
    {
        $company = Company::query()->create([
            'name' => 'Bare Contact Store',
            'slug' => 'bare-contact-store',
            'domain' => 'bare-contact.example.test',
            'domain_verified' => true,
            'invoice_prefix' => 'BAR',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        StorefrontSetting::query()->create([
            'company_id' => $company->getKey(),
            'theme_color' => '#0F766E',
            'meta_title' => $company->name,
            'is_published' => true,
        ]);

        $this->get('http://bare-contact.example.test/contact')
            ->assertOk()
            ->assertSee('Connect with Us')
            ->assertDontSee('Email Us')
            ->assertDontSee('Chat on WhatsApp')
            ->assertDontSee('Call Us')
            ->assertDontSee('Help Center')
            ->assertDontSee('Our Location')
            ->assertDontSee('Frequently Asked Questions')
            ->assertDontSee('Still Have Questions?');
    }

    public function test_storefront_page_renders_rich_html_content_cover_image_and_contact_cta(): void
    {
        $company = $this->createPublishedStorefrontCompany('Pages Store', 'pages.example.test', [
            'whatsapp_number' => '+8801700000000',
        ]);

        StorefrontPage::query()->create([
            'company_id' => $company->getKey(),
            'title' => 'About Us',
            'slug' => 'about-us',
            'excerpt' => 'Who we are and what we do.',
            'cover_image' => 'storefront/pages/about-cover.jpg',
            'content' => '<h2>Our Story</h2><p>We sell <strong>quality</strong> products.</p><ul><li>Founded in Dhaka</li></ul>',
            'is_published' => true,
        ]);

        $this->get('http://pages.example.test/pages/about-us')
            ->assertOk()
            ->assertSee('About Us')
            ->assertSee('Who we are and what we do.')
            ->assertSee('Last updated')
            ->assertSee('storage/storefront/pages/about-cover.jpg', false)
            ->assertSee('Our Story')
            ->assertSee('quality')
            ->assertSee('Founded in Dhaka')
            ->assertSee('Contact us')
            ->assertSee('href="'.route('storefront.contact').'"', false);
    }

    public function test_storefront_page_still_renders_legacy_plain_text_content(): void
    {
        $company = $this->createPublishedStorefrontCompany('Legacy Pages Store', 'legacy-pages.example.test');

        StorefrontPage::query()->create([
            'company_id' => $company->getKey(),
            'title' => 'Return Policy',
            'slug' => 'return-policy',
            'content' => "Return within 7 days.\nKeep the invoice for verification.",
            'is_published' => true,
        ]);

        $this->get('http://legacy-pages.example.test/pages/return-policy')
            ->assertOk()
            ->assertSee('Return Policy')
            ->assertSee('Return within 7 days.')
            ->assertSee('Keep the invoice for verification.');
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
            'meta_title' => $name,
            'is_published' => true,
        ], $settingOverrides));

        return $company;
    }
}
