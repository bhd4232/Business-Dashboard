<?php

namespace App\Services;

use App\Models\MetaAd;
use App\Models\MetaAdAccount;
use App\Models\MetaAdCampaign;
use App\Models\MetaAdSet;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrates the real Meta object-creation chain (Phase C):
 * Campaign -> Ad Set -> Ad Image -> Ad Creative -> Ad — one Product tied to
 * one real ad. Deliberately not wrapped in a single DB transaction: each
 * local row is persisted immediately after its own Meta call succeeds, so
 * if a later step fails (e.g. the ad-set call rejects the targeting), the
 * local database still reflects exactly what's really sitting on Meta —
 * an already-created PAUSED campaign is never silently lost track of.
 * Every object created is PAUSED (MetaMarketingApiClient's own hard-coded
 * safety rule); nothing here ever activates anything.
 */
class MetaAdsCreationService
{
    public function __construct(
        protected MetaMarketingApiClient $client,
        protected CompanyStorageService $storage,
    ) {}

    /**
     * @param  array{campaign_name: string, daily_budget: float|string, age_min: int, age_max: int, gender: string, product_id: int, headline: string, primary_text: string, description_text?: ?string, call_to_action: string}  $data
     */
    public function create(MetaAdAccount $account, array $data): MetaAdCampaign
    {
        if (! $account->canCreateAds()) {
            throw ValidationException::withMessages([
                'account' => 'This ad account needs an Access Token, Ad Account ID, and Facebook Page ID before you can create ads.',
            ]);
        }

        $product = Product::query()->findOrFail($data['product_id']);

        if (blank($product->image)) {
            throw ValidationException::withMessages([
                'product_id' => "\"{$product->name}\" has no image — add one on the product before creating an ad for it.",
            ]);
        }

        $destinationUrl = $this->destinationUrl($product);
        $dailyBudget = (float) $data['daily_budget'];
        $targeting = $this->targeting($data);

        $campaign = $this->createCampaign($account, $data['campaign_name']);
        $adSet = $this->createAdSet($account, $campaign, $data['campaign_name'].' — Ad Set', $dailyBudget, $targeting, $product);
        $this->createAd($account, $adSet, $data, $product, $destinationUrl);

        return $campaign->fresh();
    }

    protected function createCampaign(MetaAdAccount $account, string $name): MetaAdCampaign
    {
        $response = $this->client->createCampaign($account, $name);

        return MetaAdCampaign::query()->create([
            'company_id' => $account->company_id,
            'meta_ad_account_id' => $account->getKey(),
            'meta_id' => $response['id'],
            'name' => $name,
            'objective' => MetaMarketingApiClient::OBJECTIVE_TRAFFIC,
            'status' => MetaMarketingApiClient::STATUS_PAUSED,
            'effective_status' => MetaMarketingApiClient::STATUS_PAUSED,
            'special_ad_categories' => [],
            'source' => MetaAdCampaign::SOURCE_ERP,
            'created_by_user_id' => Auth::id(),
        ]);
    }

    protected function createAdSet(MetaAdAccount $account, MetaAdCampaign $campaign, string $name, float $dailyBudget, array $targeting, Product $product): MetaAdSet
    {
        $response = $this->client->createAdSet($account, $campaign->meta_id, $name, $dailyBudget, $targeting);

        return MetaAdSet::query()->create([
            'company_id' => $account->company_id,
            'meta_ad_campaign_id' => $campaign->getKey(),
            'meta_id' => $response['id'],
            'name' => $name,
            'status' => MetaMarketingApiClient::STATUS_PAUSED,
            'effective_status' => MetaMarketingApiClient::STATUS_PAUSED,
            'optimization_goal' => 'LINK_CLICKS',
            'billing_event' => 'IMPRESSIONS',
            'daily_budget' => $dailyBudget,
            'targeting' => $targeting,
            'product_id' => $product->getKey(),
            'source' => MetaAdSet::SOURCE_ERP,
        ]);
    }

    protected function createAd(MetaAdAccount $account, MetaAdSet $adSet, array $data, Product $product, string $destinationUrl): MetaAd
    {
        $imageHash = $this->client->uploadAdImage($account, $this->productImageBytes($product));

        $objectStorySpec = [
            'page_id' => $account->pageId(),
            'link_data' => [
                'link' => $destinationUrl,
                'message' => $data['primary_text'],
                'name' => $data['headline'],
                'description' => $data['description_text'] ?? '',
                'call_to_action' => [
                    'type' => $data['call_to_action'],
                    'value' => ['link' => $destinationUrl],
                ],
                'image_hash' => $imageHash,
            ],
        ];

        $creative = $this->client->createAdCreative($account, $data['headline'].' Creative', $objectStorySpec);
        $ad = $this->client->createAd($account, $adSet->meta_id, $data['headline'], $creative['id']);

        return MetaAd::query()->create([
            'company_id' => $account->company_id,
            'meta_ad_set_id' => $adSet->getKey(),
            'meta_id' => $ad['id'],
            'name' => $data['headline'],
            'status' => MetaMarketingApiClient::STATUS_PAUSED,
            'effective_status' => MetaMarketingApiClient::STATUS_PAUSED,
            'meta_creative_id' => $creative['id'],
            'headline' => $data['headline'],
            'primary_text' => $data['primary_text'],
            'description_text' => $data['description_text'] ?? null,
            'call_to_action' => $data['call_to_action'],
            'destination_url' => $destinationUrl,
            'image_hash' => $imageHash,
            'product_id' => $product->getKey(),
            'source' => MetaAd::SOURCE_ERP,
        ]);
    }

    /**
     * Bangladesh-only geo, age range, and an optional gender restriction —
     * no interest/behavior targeting (needs Meta's separate targeting-
     * search API; deliberately out of scope, see the Meta Ads plan).
     */
    protected function targeting(array $data): array
    {
        $targeting = [
            'geo_locations' => ['countries' => ['BD']],
            'age_min' => (int) $data['age_min'],
            'age_max' => (int) $data['age_max'],
        ];

        if (($data['gender'] ?? 'all') !== 'all') {
            $targeting['genders'] = [$data['gender'] === 'male' ? 1 : 2];
        }

        return $targeting;
    }

    /**
     * Same absolute-URL pattern as StorefrontCartRecord::recoveryUrl() —
     * 'https://' + the company's real storefront domain + the route's
     * relative path (never Laravel's own app URL, which isn't the
     * customer-facing domain).
     */
    protected function destinationUrl(Product $product): string
    {
        $domain = $product->company?->domain;

        if (blank($domain)) {
            throw ValidationException::withMessages([
                'product_id' => 'This product\'s company has no storefront domain configured yet.',
            ]);
        }

        return 'https://'.$domain.route('storefront.products.show', $product->slug, false);
    }

    /**
     * Raw bytes, never a public-URL fetch — robust regardless of whether
     * the storefront is publicly reachable from Meta's servers (e.g. local
     * development). Same lookup CompanyMedia::publicFileMetadata() uses.
     */
    protected function productImageBytes(Product $product): string
    {
        $location = $this->storage->locatePublic($product->image, $product->company);

        if (! $location) {
            throw ValidationException::withMessages([
                'product_id' => 'Could not locate the product image file on disk.',
            ]);
        }

        return Storage::disk($location['disk'])->get($location['path']);
    }
}
