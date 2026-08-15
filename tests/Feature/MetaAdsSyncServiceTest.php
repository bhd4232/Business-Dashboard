<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\MetaAd;
use App\Models\MetaAdAccount;
use App\Models\MetaAdCampaign;
use App\Models\MetaAdSet;
use App\Services\CompanyContext;
use App\Services\MetaAdsSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaAdsSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_upserts_campaigns_ad_sets_and_ads_with_parsed_insights(): void
    {
        $company = $this->company();
        $account = $this->account($company);
        $this->fakeFullTree();

        $result = app(MetaAdsSyncService::class)->sync($account);

        $this->assertSame(['campaigns' => 1, 'ad_sets' => 1, 'ads' => 1], $result);

        $campaign = MetaAdCampaign::query()->where('meta_id', '1001')->first();
        $this->assertNotNull($campaign);
        $this->assertSame($company->getKey(), $campaign->company_id);
        $this->assertSame('Eid Sale', $campaign->name);
        $this->assertSame('ACTIVE', $campaign->status);
        // 5000 minor units -> 50.00 major units.
        $this->assertSame('50.00', $campaign->daily_budget);
        $this->assertSame('12.50', $campaign->spend);
        $this->assertSame(1000, $campaign->impressions);
        $this->assertSame(20, $campaign->clicks);

        $adSet = MetaAdSet::query()->where('meta_id', '2001')->first();
        $this->assertNotNull($adSet);
        $this->assertSame($campaign->getKey(), $adSet->meta_ad_campaign_id);
        $this->assertSame($company->getKey(), $adSet->company_id);

        $ad = MetaAd::query()->where('meta_id', '3001')->first();
        $this->assertNotNull($ad);
        $this->assertSame($adSet->getKey(), $ad->meta_ad_set_id);
        $this->assertSame('Buy now', $ad->headline);
        $this->assertSame('abc123', $ad->image_hash);

        $this->assertNotNull($account->fresh()->last_synced_at);
        $this->assertNull($account->fresh()->last_sync_error);
        $this->assertSame(0, $account->fresh()->sync_failure_count);
    }

    public function test_sync_running_twice_updates_the_same_rows_instead_of_duplicating(): void
    {
        $account = $this->account($this->company());
        $this->fakeFullTree();

        app(MetaAdsSyncService::class)->sync($account);
        app(MetaAdsSyncService::class)->sync($account);

        $this->assertSame(1, MetaAdCampaign::query()->count());
        $this->assertSame(1, MetaAdSet::query()->count());
        $this->assertSame(1, MetaAd::query()->count());
    }

    public function test_a_failing_account_stamps_the_error_without_throwing_or_touching_other_accounts(): void
    {
        $company = $this->company();
        $account = $this->account($company);

        Http::fake([
            'https://graph.facebook.com/*/campaigns*' => Http::response(['error' => ['message' => 'Invalid OAuth access token.']], 400),
        ]);

        $result = app(MetaAdsSyncService::class)->sync($account);

        $this->assertSame(['campaigns' => 0, 'ad_sets' => 0, 'ads' => 0], $result);
        $account->refresh();
        $this->assertSame(1, $account->sync_failure_count);
        $this->assertStringContainsString('Invalid OAuth access token', (string) $account->last_sync_error);
        $this->assertSame(0, MetaAdCampaign::query()->count());
    }

    protected function fakeFullTree(): void
    {
        Http::fake([
            'https://graph.facebook.com/*/act_123456789/campaigns*' => Http::response(['data' => [
                [
                    'id' => '1001', 'name' => 'Eid Sale', 'status' => 'ACTIVE', 'effective_status' => 'ACTIVE',
                    'daily_budget' => '5000',
                    'insights' => ['data' => [['spend' => '12.50', 'impressions' => '1000', 'clicks' => '20', 'ctr' => '2.0', 'cpc' => '0.625', 'reach' => '900']]],
                ],
            ]]),
            'https://graph.facebook.com/*/1001/adsets*' => Http::response(['data' => [
                ['id' => '2001', 'name' => 'Broad Audience', 'status' => 'ACTIVE'],
            ]]),
            'https://graph.facebook.com/*/2001/ads*' => Http::response(['data' => [
                ['id' => '3001', 'name' => 'Carousel Ad', 'status' => 'ACTIVE', 'creative' => ['id' => '9001', 'title' => 'Buy now', 'body' => 'Great deal', 'image_hash' => 'abc123']],
            ]]),
        ]);
    }

    protected function company(): Company
    {
        $company = Company::query()->create([
            'name' => 'Meta Sync Co', 'slug' => 'meta-sync-co',
            'invoice_prefix' => 'MSC', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);

        return $company;
    }

    protected function account(Company $company): MetaAdAccount
    {
        return MetaAdAccount::query()->create([
            'company_id' => $company->getKey(),
            'name' => 'Main Ad Account',
            'credentials' => [
                'app_id' => 'test-app-id',
                'app_secret' => 'test-app-secret',
                'access_token' => 'test-access-token',
                'ad_account_id' => '123456789',
            ],
            'is_active' => true,
        ]);
    }
}
