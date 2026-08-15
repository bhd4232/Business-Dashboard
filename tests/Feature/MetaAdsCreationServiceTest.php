<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\MetaAd;
use App\Models\MetaAdAccount;
use App\Models\MetaAdCampaign;
use App\Models\MetaAdSet;
use App\Models\Product;
use App\Services\CompanyContext;
use App\Services\CompanyStorageService;
use App\Services\MetaAdsCreationService;
use App\Services\StorageSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Throwable;

class MetaAdsCreationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_full_chain_creates_all_three_local_rows_tied_to_the_product(): void
    {
        $company = $this->company();
        $account = $this->account($company, ['page_id' => '999888777']);
        $product = $this->product($company);

        $this->fakeSuccessfulChain();

        $campaign = app(MetaAdsCreationService::class)->create($account, $this->formData($product));

        $this->assertSame(MetaAdCampaign::SOURCE_ERP, $campaign->source);
        $this->assertSame('PAUSED', $campaign->status);
        $this->assertSame('c-1', $campaign->meta_id);
        $this->assertSame('OUTCOME_TRAFFIC', $campaign->objective);

        $adSet = MetaAdSet::query()->where('meta_ad_campaign_id', $campaign->getKey())->first();
        $this->assertNotNull($adSet);
        $this->assertSame($product->getKey(), $adSet->product_id);
        $this->assertSame(MetaAdSet::SOURCE_ERP, $adSet->source);
        $this->assertSame('as-1', $adSet->meta_id);
        $this->assertSame('25.00', $adSet->daily_budget);
        $this->assertSame(['countries' => ['BD']], $adSet->targeting['geo_locations']);

        $ad = MetaAd::query()->where('meta_ad_set_id', $adSet->getKey())->first();
        $this->assertNotNull($ad);
        $this->assertSame($product->getKey(), $ad->product_id);
        $this->assertSame(MetaAd::SOURCE_ERP, $ad->source);
        $this->assertSame('a-1', $ad->meta_id);
        $this->assertSame('PAUSED', $ad->status);
        $this->assertSame('image-hash-1', $ad->image_hash);
        $this->assertSame('https://creation-co.example.com/product/'.$product->slug, $ad->destination_url);
    }

    public function test_a_mid_chain_failure_leaves_the_already_created_campaign_in_place(): void
    {
        $company = $this->company();
        $account = $this->account($company, ['page_id' => '999888777']);
        $product = $this->product($company);

        Http::fake([
            'https://graph.facebook.com/*/act_123456789/campaigns' => Http::response(['id' => 'c-1']),
            'https://graph.facebook.com/*/act_123456789/adsets' => Http::response(['error' => ['message' => 'Invalid targeting']], 400),
        ]);

        try {
            app(MetaAdsCreationService::class)->create($account, $this->formData($product));
            $this->fail('Expected the ad-set creation to throw.');
        } catch (Throwable) {
            // Expected — assertions below are what actually matter.
        }

        $this->assertSame(1, MetaAdCampaign::query()->count());
        $this->assertSame('c-1', MetaAdCampaign::query()->first()->meta_id);
        $this->assertSame(0, MetaAdSet::query()->count());
        $this->assertSame(0, MetaAd::query()->count());
    }

    public function test_an_account_missing_the_page_id_is_rejected_before_any_meta_call(): void
    {
        $company = $this->company();
        $account = $this->account($company);
        $product = $this->product($company);

        Http::fake();

        try {
            app(MetaAdsCreationService::class)->create($account, $this->formData($product));
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('account', $exception->errors());
        }

        Http::assertNothingSent();
    }

    public function test_a_product_without_an_image_is_rejected_before_any_meta_call(): void
    {
        $company = $this->company();
        $account = $this->account($company, ['page_id' => '999888777']);
        $product = $this->product($company);
        $product->forceFill(['image' => null])->save();

        Http::fake();

        try {
            app(MetaAdsCreationService::class)->create($account, $this->formData($product));
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('product_id', $exception->errors());
        }

        Http::assertNothingSent();
    }

    public function test_gender_restriction_is_added_to_targeting_only_when_not_all(): void
    {
        $company = $this->company();
        $account = $this->account($company, ['page_id' => '999888777']);
        $product = $this->product($company);
        $this->fakeSuccessfulChain();

        $campaign = app(MetaAdsCreationService::class)->create($account, [
            ...$this->formData($product),
            'gender' => 'female',
        ]);

        $adSet = MetaAdSet::query()->where('meta_ad_campaign_id', $campaign->getKey())->first();
        $this->assertSame([2], $adSet->targeting['genders']);
    }

    protected function fakeSuccessfulChain(): void
    {
        Http::fake([
            'https://graph.facebook.com/*/act_123456789/campaigns' => Http::response(['id' => 'c-1']),
            'https://graph.facebook.com/*/act_123456789/adsets' => Http::response(['id' => 'as-1']),
            'https://graph.facebook.com/*/act_123456789/adimages' => Http::response(['images' => ['x.jpg' => ['hash' => 'image-hash-1']]]),
            'https://graph.facebook.com/*/act_123456789/adcreatives' => Http::response(['id' => 'cr-1']),
            'https://graph.facebook.com/*/act_123456789/ads' => Http::response(['id' => 'a-1']),
        ]);
    }

    /** @return array<string, mixed> */
    protected function formData(Product $product): array
    {
        return [
            'campaign_name' => 'Eid Sale',
            'daily_budget' => 25,
            'age_min' => 18,
            'age_max' => 45,
            'gender' => 'all',
            'product_id' => $product->getKey(),
            'headline' => 'Buy Now',
            'primary_text' => 'Great product at a great price.',
            'description_text' => null,
            'call_to_action' => 'SHOP_NOW',
        ];
    }

    protected function company(): Company
    {
        $company = Company::query()->create([
            'name' => 'Creation Co', 'slug' => 'creation-co', 'domain' => 'creation-co.example.com',
            'invoice_prefix' => 'CRC', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);

        return $company;
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

    protected function product(Company $company): Product
    {
        Storage::fake('public');
        app(StorageSettingsService::class)->save(['enabled' => false]);

        $path = app(CompanyStorageService::class)->putPublic($company, 'products', 'photo.jpg', 'fake-image-bytes');

        return Product::query()->create([
            'company_id' => $company->getKey(),
            'name' => 'Test Product',
            'sku' => 'CRC-TEST',
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
