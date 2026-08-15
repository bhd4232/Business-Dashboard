<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\MetaAdAccount;
use App\Services\CompanyContext;
use App\Services\MetaMarketingApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MetaMarketingApiClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_calls_without_credentials_are_rejected_before_any_request_is_made(): void
    {
        Http::fake();
        $account = $this->account($this->company(), ['app_id' => null, 'access_token' => null, 'ad_account_id' => null]);

        try {
            app(MetaMarketingApiClient::class)->campaigns($account);
            $this->fail('Expected a ValidationException for missing credentials.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('credentials', $exception->errors());
        }

        Http::assertNothingSent();
    }

    public function test_campaigns_requests_nested_insights_and_parses_the_response(): void
    {
        $account = $this->account($this->company());

        Http::fake([
            'https://graph.facebook.com/*/act_123456789/campaigns*' => Http::response([
                'data' => [
                    [
                        'id' => '1001',
                        'name' => 'Eid Sale',
                        'status' => 'ACTIVE',
                        'effective_status' => 'ACTIVE',
                        'daily_budget' => '5000',
                        'insights' => ['data' => [
                            ['spend' => '12.50', 'impressions' => '1000', 'clicks' => '20', 'ctr' => '2.0', 'cpc' => '0.625', 'reach' => '900', 'actions' => [['action_type' => 'purchase', 'value' => '3']]],
                        ]],
                    ],
                ],
            ]),
        ]);

        $campaigns = app(MetaMarketingApiClient::class)->campaigns($account);

        $this->assertCount(1, $campaigns);
        $this->assertSame('Eid Sale', $campaigns[0]['name']);
        $this->assertSame('12.50', $campaigns[0]['insights']['data'][0]['spend']);

        Http::assertSent(function ($request): bool {
            $url = urldecode((string) $request->url());

            return str_contains($url, '/act_123456789/campaigns')
                && str_contains($url, 'access_token=test-access-token')
                && str_contains($url, 'insights.date_preset(last_30d)');
        });
    }

    public function test_ad_sets_and_ads_hit_the_object_scoped_edges(): void
    {
        $account = $this->account($this->company());

        Http::fake([
            'https://graph.facebook.com/*/1001/adsets*' => Http::response(['data' => [
                ['id' => '2001', 'name' => 'Broad Audience', 'status' => 'PAUSED'],
            ]]),
            'https://graph.facebook.com/*/2001/ads*' => Http::response(['data' => [
                ['id' => '3001', 'name' => 'Carousel Ad', 'status' => 'PAUSED', 'creative' => ['id' => '9001', 'title' => 'Buy now', 'body' => 'Great deal', 'image_hash' => 'abc123']],
            ]]),
        ]);

        $client = app(MetaMarketingApiClient::class);
        $adSets = $client->adSets($account, '1001');
        $ads = $client->ads($account, '2001');

        $this->assertSame('Broad Audience', $adSets[0]['name']);
        $this->assertSame('Carousel Ad', $ads[0]['name']);
        $this->assertSame('abc123', $ads[0]['creative']['image_hash']);
    }

    public function test_verify_credentials_hits_the_account_root_and_returns_currency(): void
    {
        $account = $this->account($this->company());

        Http::fake([
            'https://graph.facebook.com/*/act_123456789*' => Http::response([
                'name' => 'Main Store', 'currency' => 'USD', 'account_status' => 1,
            ]),
        ]);

        $result = app(MetaMarketingApiClient::class)->verifyCredentials($account);

        $this->assertSame('Main Store', $result['name']);
        $this->assertSame('USD', $result['currency']);
    }

    public function test_update_status_posts_the_status_form_param_to_the_object_id(): void
    {
        $account = $this->account($this->company());

        Http::fake([
            'https://graph.facebook.com/*/1001' => Http::response(['success' => true]),
        ]);

        $result = app(MetaMarketingApiClient::class)->updateStatus($account, '1001', MetaMarketingApiClient::STATUS_PAUSED);

        $this->assertTrue($result['success']);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && str_ends_with((string) $request->url(), '/1001')
                && $request['status'] === 'PAUSED'
                && $request['access_token'] === 'test-access-token';
        });
    }

    public function test_update_budget_converts_major_units_to_meta_minor_units(): void
    {
        $account = $this->account($this->company());

        Http::fake([
            'https://graph.facebook.com/*/1001' => Http::response(['success' => true]),
        ]);

        app(MetaMarketingApiClient::class)->updateBudget($account, '1001', ['daily_budget' => 25.50]);

        Http::assertSent(function ($request): bool {
            // 25.50 major units (e.g. USD) -> 2550 minor units (cents).
            return $request->method() === 'POST'
                && (int) $request['daily_budget'] === 2550
                && ! array_key_exists('lifetime_budget', $request->data());
        });
    }

    public function test_write_back_calls_without_credentials_are_also_rejected(): void
    {
        Http::fake();
        $account = $this->account($this->company(), ['access_token' => null]);

        try {
            app(MetaMarketingApiClient::class)->updateStatus($account, '1001', MetaMarketingApiClient::STATUS_ACTIVE);
            $this->fail('Expected a ValidationException for missing credentials.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('credentials', $exception->errors());
        }

        Http::assertNothingSent();
    }

    public function test_create_campaign_always_sends_paused_status_and_empty_special_ad_categories(): void
    {
        $account = $this->account($this->company());

        Http::fake([
            'https://graph.facebook.com/*/act_123456789/campaigns' => Http::response(['id' => '5001']),
        ]);

        $result = app(MetaMarketingApiClient::class)->createCampaign($account, 'Eid Sale');

        $this->assertSame('5001', $result['id']);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && str_ends_with((string) $request->url(), '/act_123456789/campaigns')
                && $request['name'] === 'Eid Sale'
                && $request['objective'] === MetaMarketingApiClient::OBJECTIVE_TRAFFIC
                && $request['status'] === 'PAUSED'
                && json_decode((string) $request['special_ad_categories'], true) === [];
        });
    }

    public function test_create_ad_set_json_encodes_targeting_and_converts_budget(): void
    {
        $account = $this->account($this->company());

        Http::fake([
            'https://graph.facebook.com/*/act_123456789/adsets' => Http::response(['id' => '5002']),
        ]);

        $targeting = ['geo_locations' => ['countries' => ['BD']], 'age_min' => 18, 'age_max' => 45];
        app(MetaMarketingApiClient::class)->createAdSet($account, '5001', 'Eid Sale — Ad Set', 25.00, $targeting);

        Http::assertSent(function ($request) use ($targeting): bool {
            return $request['campaign_id'] === '5001'
                && (int) $request['daily_budget'] === 2500
                && $request['billing_event'] === 'IMPRESSIONS'
                && $request['optimization_goal'] === 'LINK_CLICKS'
                && $request['status'] === 'PAUSED'
                && json_decode((string) $request['targeting'], true) === $targeting;
        });
    }

    public function test_upload_ad_image_sends_base64_bytes_and_returns_the_hash(): void
    {
        $account = $this->account($this->company());

        Http::fake([
            'https://graph.facebook.com/*/act_123456789/adimages' => Http::response([
                'images' => ['product.jpg' => ['hash' => 'abc123hash', 'url' => 'https://example.com/img.jpg']],
            ]),
        ]);

        $hash = app(MetaMarketingApiClient::class)->uploadAdImage($account, 'raw-bytes-here');

        $this->assertSame('abc123hash', $hash);

        Http::assertSent(fn ($request): bool => $request['bytes'] === base64_encode('raw-bytes-here'));
    }

    public function test_upload_ad_image_throws_when_meta_does_not_return_a_hash(): void
    {
        $account = $this->account($this->company());

        Http::fake([
            'https://graph.facebook.com/*/act_123456789/adimages' => Http::response(['images' => []]),
        ]);

        $this->expectException(\RuntimeException::class);
        app(MetaMarketingApiClient::class)->uploadAdImage($account, 'bytes');
    }

    public function test_create_ad_creative_json_encodes_the_object_story_spec(): void
    {
        $account = $this->account($this->company());

        Http::fake([
            'https://graph.facebook.com/*/act_123456789/adcreatives' => Http::response(['id' => '5003']),
        ]);

        $spec = ['page_id' => '999', 'link_data' => ['link' => 'https://example.com', 'image_hash' => 'abc']];
        app(MetaMarketingApiClient::class)->createAdCreative($account, 'Eid Sale Creative', $spec);

        Http::assertSent(fn ($request): bool => json_decode((string) $request['object_story_spec'], true) === $spec);
    }

    public function test_create_ad_always_sends_paused_status_and_references_the_creative(): void
    {
        $account = $this->account($this->company());

        Http::fake([
            'https://graph.facebook.com/*/act_123456789/ads' => Http::response(['id' => '5004']),
        ]);

        app(MetaMarketingApiClient::class)->createAd($account, '5002', 'Eid Sale', '5003');

        Http::assertSent(function ($request): bool {
            return $request['adset_id'] === '5002'
                && $request['status'] === 'PAUSED'
                && json_decode((string) $request['creative'], true) === ['creative_id' => '5003'];
        });
    }

    public function test_pixel_requests_the_documented_identity_fields(): void
    {
        $account = $this->account($this->company());

        Http::fake([
            'https://graph.facebook.com/*/pixel-1*' => Http::response([
                'id' => 'pixel-1', 'name' => 'Main Pixel', 'creation_time' => '2026-01-01T00:00:00+0000',
                'last_fired_time' => '2026-08-14T00:00:00+0000', 'is_unavailable' => false,
            ]),
        ]);

        $result = app(MetaMarketingApiClient::class)->pixel($account, 'pixel-1');

        $this->assertSame('Main Pixel', $result['name']);

        Http::assertSent(function ($request): bool {
            $url = urldecode((string) $request->url());

            return str_contains($url, '/pixel-1')
                && str_contains($url, 'fields=id,name,creation_time,last_fired_time,is_unavailable')
                && str_contains($url, 'access_token=test-access-token');
        });
    }

    public function test_pixel_event_stats_requests_the_aggregation_and_time_window(): void
    {
        $account = $this->account($this->company());

        Http::fake([
            'https://graph.facebook.com/*/pixel-1/stats*' => Http::response([
                'data' => [['name' => 'Purchase', 'count' => 12]],
            ]),
        ]);

        $rows = app(MetaMarketingApiClient::class)->pixelEventStats($account, 'pixel-1', 1000, 2000);

        $this->assertSame('Purchase', $rows[0]['name']);

        Http::assertSent(function ($request): bool {
            $url = urldecode((string) $request->url());

            return str_contains($url, '/pixel-1/stats')
                && str_contains($url, 'aggregation=event')
                && str_contains($url, 'start_time=1000')
                && str_contains($url, 'end_time=2000');
        });
    }

    public function test_custom_audiences_requests_the_documented_fields(): void
    {
        $account = $this->account($this->company());

        Http::fake([
            'https://graph.facebook.com/*/act_123456789/customaudiences*' => Http::response([
                'data' => [['id' => 'aud-1', 'name' => 'Site Visitors', 'subtype' => 'WEBSITE']],
            ]),
        ]);

        $result = app(MetaMarketingApiClient::class)->customAudiences($account);

        $this->assertSame('Site Visitors', $result[0]['name']);

        Http::assertSent(function ($request): bool {
            $url = urldecode((string) $request->url());

            return str_contains($url, '/act_123456789/customaudiences')
                && str_contains($url, 'fields=id,name,subtype,description,approximate_count_lower_bound,approximate_count_upper_bound,delivery_status,operation_status,time_created,time_updated');
        });
    }

    public function test_create_website_custom_audience_json_encodes_the_rule_and_omits_filter_when_no_url_contains(): void
    {
        $account = $this->account($this->company());

        Http::fake([
            'https://graph.facebook.com/*/act_123456789/customaudiences' => Http::response(['id' => 'aud-1']),
        ]);

        $result = app(MetaMarketingApiClient::class)->createWebsiteCustomAudience($account, 'Site Visitors', 'pixel-1', 30);

        $this->assertSame('aud-1', $result['id']);

        Http::assertSent(function ($request): bool {
            $rule = json_decode((string) $request['rule'], true);

            return $request['name'] === 'Site Visitors'
                && $request['subtype'] === 'WEBSITE'
                && $rule['inclusions']['rules'][0]['event_sources'][0]['id'] === 'pixel-1'
                && $rule['inclusions']['rules'][0]['retention_seconds'] === 30 * 86400
                && ! array_key_exists('filter', $rule['inclusions']['rules'][0]);
        });
    }

    public function test_create_website_custom_audience_includes_the_filter_when_url_contains_is_given(): void
    {
        $account = $this->account($this->company());

        Http::fake([
            'https://graph.facebook.com/*/act_123456789/customaudiences' => Http::response(['id' => 'aud-1']),
        ]);

        app(MetaMarketingApiClient::class)->createWebsiteCustomAudience($account, 'Shoe Shoppers', 'pixel-1', 14, 'shoes');

        Http::assertSent(function ($request): bool {
            $rule = json_decode((string) $request['rule'], true);
            $filter = $rule['inclusions']['rules'][0]['filter']['filters'][0] ?? null;

            return $filter !== null
                && $filter['field'] === 'url'
                && $filter['operator'] === 'i_contains'
                && $filter['value'] === 'shoes';
        });
    }

    public function test_create_lookalike_audience_json_encodes_the_lookalike_spec_and_converts_ratio_to_a_fraction(): void
    {
        $account = $this->account($this->company());

        Http::fake([
            'https://graph.facebook.com/*/act_123456789/customaudiences' => Http::response(['id' => 'aud-2']),
        ]);

        app(MetaMarketingApiClient::class)->createLookalikeAudience($account, 'Lookalike 1%', 'aud-1', 'BD', 1.0);

        Http::assertSent(function ($request): bool {
            $spec = json_decode((string) $request['lookalike_spec'], true);

            return $request['subtype'] === 'LOOKALIKE'
                && $request['origin_audience_id'] === 'aud-1'
                && $spec['country'] === 'BD'
                && $spec['ratio'] === 0.01;
        });
    }

    public function test_delete_audience_sends_a_delete_request_with_the_access_token(): void
    {
        $account = $this->account($this->company());

        Http::fake([
            'https://graph.facebook.com/*/aud-1' => Http::response(['success' => true]),
        ]);

        $result = app(MetaMarketingApiClient::class)->deleteAudience($account, 'aud-1');

        $this->assertTrue($result['success']);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'DELETE'
                && str_ends_with((string) $request->url(), '/aud-1')
                && $request['access_token'] === 'test-access-token';
        });
    }

    protected function company(): Company
    {
        $company = Company::query()->create([
            'name' => 'Meta Client Co', 'slug' => 'meta-client-co',
            'invoice_prefix' => 'MCC', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
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
}
