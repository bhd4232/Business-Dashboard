<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\StorefrontSetting;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontOfferCountdownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_active_offer_shows_on_homepage(): void
    {
        $company = $this->createStore('offer.example.test', [
            'offer_title' => 'Flash Sale',
            'offer_discount_percent' => 30,
            'offer_ends_at' => now()->addDay(),
        ]);

        $this->get('http://offer.example.test/')
            ->assertOk()
            ->assertSee('Flash Sale')
            ->assertSee('up to 30% off');
    }

    public function test_expired_offer_is_hidden(): void
    {
        $company = $this->createStore('offer-expired.example.test', [
            'offer_title' => 'Old Sale',
            'offer_discount_percent' => 50,
            'offer_ends_at' => now()->subDay(),
        ]);

        $this->get('http://offer-expired.example.test/')
            ->assertOk()
            ->assertDontSee('Old Sale');
    }

    public function test_no_offer_configured_shows_nothing(): void
    {
        $company = $this->createStore('offer-none.example.test');

        $this->get('http://offer-none.example.test/')
            ->assertOk()
            ->assertDontSee('up to');
    }

    private function createStore(string $domain, array $settingOverrides = []): Company
    {
        $company = Company::query()->create([
            'name' => 'Store '.$domain,
            'slug' => str($domain)->slug()->toString(),
            'domain' => $domain,
            'domain_verified' => true,
            'invoice_prefix' => 'OFR',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        StorefrontSetting::query()->create(array_merge([
            'company_id' => $company->getKey(),
            'theme_color' => '#0F766E',
            'is_published' => true,
        ], $settingOverrides));

        return $company;
    }
}
