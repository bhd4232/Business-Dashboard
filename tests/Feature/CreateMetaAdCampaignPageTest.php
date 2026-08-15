<?php

namespace Tests\Feature;

use App\Filament\Pages\CreateMetaAdCampaign;
use App\Models\Company;
use App\Models\MetaAdAccount;
use App\Models\MetaAdCampaign;
use App\Models\MetaAdProposal;
use App\Models\Product;
use App\Models\User;
use App\Services\CompanyContext;
use App\Services\CompanyStorageService;
use App\Services\StorageSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CreateMetaAdCampaignPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_products_with_an_image_from_the_current_company_are_offered(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
        $this->account($company, ['page_id' => '999']);

        $withImage = $this->product($company, 'With Image', hasImage: true);
        $withoutImage = $this->product($company, 'Without Image', hasImage: false);

        $otherCompany = Company::query()->create([
            'name' => 'Other Ads Co', 'slug' => 'other-create-co', 'domain' => 'other-create.example.com',
            'invoice_prefix' => 'OCC', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        app(CompanyContext::class)->set($otherCompany);
        $otherProduct = $this->product($otherCompany, 'Other Company Product', hasImage: true);
        app(CompanyContext::class)->set($company);

        $component = Livewire::test(CreateMetaAdCampaign::class);
        $products = $component->instance()->eligibleProducts();

        $this->assertTrue($products->contains('id', $withImage->getKey()));
        $this->assertFalse($products->contains('id', $withoutImage->getKey()));
        $this->assertFalse($products->contains('id', $otherProduct->getKey()));
    }

    public function test_submitting_creates_the_campaign_and_redirects_to_the_dashboard(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
        $account = $this->account($company, ['page_id' => '999']);
        $product = $this->product($company, 'Promo Product', hasImage: true);

        Http::fake([
            'https://graph.facebook.com/*/act_123456789/campaigns' => Http::response(['id' => 'c-1']),
            'https://graph.facebook.com/*/act_123456789/adsets' => Http::response(['id' => 'as-1']),
            'https://graph.facebook.com/*/act_123456789/adimages' => Http::response(['images' => ['x.jpg' => ['hash' => 'hash-1']]]),
            'https://graph.facebook.com/*/act_123456789/adcreatives' => Http::response(['id' => 'cr-1']),
            'https://graph.facebook.com/*/act_123456789/ads' => Http::response(['id' => 'a-1']),
        ]);

        Livewire::test(CreateMetaAdCampaign::class)
            ->fillForm([
                'account_id' => $account->getKey(),
                'campaign_name' => 'Promo Campaign',
                'daily_budget' => 20,
                'age_min' => 18,
                'age_max' => 55,
                'gender' => 'all',
                'product_id' => $product->getKey(),
                'headline' => 'Buy Now',
                'primary_text' => 'Great deal today.',
                'description_text' => null,
                'call_to_action' => 'SHOP_NOW',
            ])
            ->call('submit')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('meta_ad_campaigns', [
            'meta_ad_account_id' => $account->getKey(),
            'meta_id' => 'c-1',
            'name' => 'Promo Campaign',
            'status' => 'PAUSED',
            'source' => 'erp',
        ]);
    }

    public function test_submitting_without_page_id_configured_fails_cleanly(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
        $account = $this->account($company); // no page_id
        $product = $this->product($company, 'Promo Product', hasImage: true);

        Http::fake();

        Livewire::test(CreateMetaAdCampaign::class)
            ->fillForm([
                'account_id' => $account->getKey(),
                'campaign_name' => 'Promo Campaign',
                'daily_budget' => 20,
                'age_min' => 18,
                'age_max' => 55,
                'gender' => 'all',
                'product_id' => $product->getKey(),
                'headline' => 'Buy Now',
                'primary_text' => 'Great deal today.',
                'call_to_action' => 'SHOP_NOW',
            ])
            ->call('submit')
            ->assertNotified();

        $this->assertSame(0, MetaAdCampaign::query()->count());
        Http::assertNothingSent();
    }

    public function test_opening_from_a_proposal_prefills_the_form(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
        $account = $this->account($company, ['page_id' => '999']);
        $product = $this->product($company, 'Promo Product', hasImage: true);

        $proposal = MetaAdProposal::query()->create([
            'company_id' => $company->getKey(),
            'meta_ad_account_id' => $account->getKey(),
            'product_id' => $product->getKey(),
            'is_recommended' => true,
            'suggested_budget' => 75,
            'suggested_duration_days' => 10,
            'headline' => 'AI Headline',
            'primary_text' => 'AI primary text.',
            'description_text' => 'AI description.',
            'call_to_action' => 'BUY_NOW',
            'targeting' => ['age_min' => 20, 'age_max' => 40, 'gender' => 'female'],
            'reasoning_text' => 'Strong margin and recent sales.',
            'ai_provider' => 'anthropic',
            'ai_model' => 'claude-test',
            'status' => MetaAdProposal::STATUS_DRAFT,
        ]);

        Livewire::withQueryParams(['proposalId' => $proposal->getKey()]);

        $component = Livewire::test(CreateMetaAdCampaign::class);

        $this->assertSame($proposal->getKey(), $component->get('proposalId'));
        $this->assertEquals($account->getKey(), $component->get('data.account_id'));
        $this->assertEquals($product->getKey(), $component->get('data.product_id'));
        $this->assertSame(75.0, (float) $component->get('data.daily_budget'));
        $this->assertEquals(20, $component->get('data.age_min'));
        $this->assertEquals(40, $component->get('data.age_max'));
        $this->assertSame('female', $component->get('data.gender'));
        $this->assertSame('AI Headline', $component->get('data.headline'));
        $this->assertSame('BUY_NOW', $component->get('data.call_to_action'));
    }

    public function test_submitting_a_proposal_backed_campaign_marks_it_launched(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
        $account = $this->account($company, ['page_id' => '999']);
        $product = $this->product($company, 'Promo Product', hasImage: true);

        $proposal = MetaAdProposal::query()->create([
            'company_id' => $company->getKey(),
            'meta_ad_account_id' => $account->getKey(),
            'product_id' => $product->getKey(),
            'is_recommended' => true,
            'suggested_budget' => 75,
            'status' => MetaAdProposal::STATUS_DRAFT,
        ]);

        Http::fake([
            'https://graph.facebook.com/*/act_123456789/campaigns' => Http::response(['id' => 'c-1']),
            'https://graph.facebook.com/*/act_123456789/adsets' => Http::response(['id' => 'as-1']),
            'https://graph.facebook.com/*/act_123456789/adimages' => Http::response(['images' => ['x.jpg' => ['hash' => 'hash-1']]]),
            'https://graph.facebook.com/*/act_123456789/adcreatives' => Http::response(['id' => 'cr-1']),
            'https://graph.facebook.com/*/act_123456789/ads' => Http::response(['id' => 'a-1']),
        ]);

        Livewire::withQueryParams(['proposalId' => $proposal->getKey()]);

        Livewire::test(CreateMetaAdCampaign::class)
            ->fillForm([
                'account_id' => $account->getKey(),
                'campaign_name' => 'AI Campaign',
                'daily_budget' => 75,
                'age_min' => 18,
                'age_max' => 55,
                'gender' => 'all',
                'product_id' => $product->getKey(),
                'headline' => 'Buy Now',
                'primary_text' => 'Great deal today.',
                'description_text' => null,
                'call_to_action' => 'SHOP_NOW',
            ])
            ->call('submit')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $campaign = MetaAdCampaign::query()->sole();
        $this->assertSame(MetaAdProposal::STATUS_LAUNCHED, $proposal->fresh()->status);
        $this->assertSame($campaign->getKey(), $proposal->fresh()->meta_ad_campaign_id);
    }

    /** @return array{0: Company, 1: User} */
    protected function makeCompanyAndUser(): array
    {
        $company = Company::query()->create([
            'name' => 'Create Campaign Co', 'slug' => 'create-campaign-co', 'domain' => 'create-campaign.example.com',
            'invoice_prefix' => 'CCC', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);

        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        return [$company, $user];
    }

    protected function account(Company $company, array $credentialOverrides = []): MetaAdAccount
    {
        return MetaAdAccount::query()->create([
            'company_id' => $company->getKey(),
            'name' => 'Main Ad Account',
            'credentials' => array_merge([
                'app_id' => 'test-app-id',
                'app_secret' => 'test-app-secret',
                'access_token' => 'test-access-token',
                'ad_account_id' => '123456789',
            ], $credentialOverrides),
            'is_active' => true,
        ]);
    }

    protected function product(Company $company, string $name, bool $hasImage): Product
    {
        $path = null;

        if ($hasImage) {
            Storage::fake('public');
            app(StorageSettingsService::class)->save(['enabled' => false]);
            $path = app(CompanyStorageService::class)->putPublic($company, 'products', 'photo.jpg', 'fake-image-bytes');
        }

        return Product::query()->create([
            'company_id' => $company->getKey(),
            'name' => $name,
            'sku' => 'CCC-'.str($name)->slug()->upper(),
            'price' => 500,
            'sale_price' => 450,
            'cost_price' => 300,
            'stock' => 10,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'image' => $path,
        ]);
    }
}
