<?php

namespace Tests\Feature;

use App\Filament\Resources\Offers\OfferResource;
use App\Filament\Resources\Offers\Pages\EditOffer;
use App\Filament\Resources\Offers\Pages\ListOffers;
use App\Models\Company;
use App\Models\Offer;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the "Preview" / "Open Page" actions added to the Offers list and
 * edit page — the owner had no way to see a landing page (AI-generated or
 * hand-built) without them. Mirrors StorefrontSettingResource's own
 * previewUrl()/publicUrl() pattern: preview always works (admin-only, shows
 * drafts too, via the domain-scoped storefront preview routes),
 * "Open Page" only once the offer is actually Published with a domain set
 * (the live public route 404s otherwise).
 */
class OfferResourceLandingPageActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_url_is_reachable_for_a_draft_offer(): void
    {
        $company = $this->company(['domain' => null]);
        $offer = $this->offer($company, Offer::STATUS_DRAFT);

        $url = OfferResource::previewUrl($offer);

        $this->assertSame(
            route('storefront.preview.offers.show', ['company' => $company->slug, 'slug' => $offer->slug]),
            $url,
        );

        $this->actingAs($this->superAdmin($company))
            ->get($url)
            ->assertOk();
    }

    public function test_public_url_only_targets_a_published_offer_with_a_domain(): void
    {
        $company = $this->company(['domain' => 'zamzamgadgetbd.com', 'domain_verified' => true]);
        $offer = $this->offer($company, Offer::STATUS_PUBLISHED);

        $this->assertSame(
            'https://zamzamgadgetbd.com/offers/'.$offer->slug,
            OfferResource::publicUrl($offer),
        );
    }

    public function test_edit_offer_page_hides_open_page_for_a_draft_offer_but_always_shows_preview(): void
    {
        $company = $this->company(['domain' => 'zamzamgadgetbd.com', 'domain_verified' => true]);
        $offer = $this->offer($company, Offer::STATUS_DRAFT);
        $this->actingAs($this->superAdmin($company));

        Livewire::test(EditOffer::class, ['record' => $offer->getKey()])
            ->assertActionVisible('previewLandingPage')
            ->assertActionHidden('openLandingPage');
    }

    public function test_edit_offer_page_shows_open_page_once_published_with_a_domain(): void
    {
        $company = $this->company(['domain' => 'zamzamgadgetbd.com', 'domain_verified' => true]);
        $offer = $this->offer($company, Offer::STATUS_PUBLISHED);
        $this->actingAs($this->superAdmin($company));

        Livewire::test(EditOffer::class, ['record' => $offer->getKey()])
            ->assertActionVisible('previewLandingPage')
            ->assertActionVisible('openLandingPage');
    }

    public function test_offers_list_hides_open_page_when_the_company_has_no_domain(): void
    {
        $company = $this->company(['domain' => null]);
        $offer = $this->offer($company, Offer::STATUS_PUBLISHED);
        $this->actingAs($this->superAdmin($company));

        Livewire::test(ListOffers::class)
            ->assertTableActionVisible('previewLandingPage', $offer)
            ->assertTableActionHidden('openLandingPage', $offer);
    }

    protected function company(array $overrides = []): Company
    {
        $company = Company::query()->create(array_merge([
            'name' => 'Offer Landing Co',
            'slug' => 'offer-landing-co-'.uniqid(),
            'invoice_prefix' => 'OLC'.random_int(100, 999),
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ], $overrides));

        app(CompanyContext::class)->set($company);

        return $company;
    }

    protected function offer(Company $company, string $status): Offer
    {
        return Offer::query()->create([
            'company_id' => $company->getKey(),
            'type' => Offer::TYPE_SINGLE,
            'title' => 'Test Offer',
            'status' => $status,
            'price_mode' => Offer::PRICE_MODE_AUTO_SUM,
        ]);
    }

    protected function superAdmin(Company $company): User
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $user->companies()->attach($company, ['role' => 'super_admin', 'is_default' => true]);

        return $user;
    }
}
