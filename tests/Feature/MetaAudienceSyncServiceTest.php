<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\MetaAdAccount;
use App\Models\MetaAudience;
use App\Services\CompanyContext;
use App\Services\MetaAudienceSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaAudienceSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_upserts_audiences_by_meta_id(): void
    {
        $company = $this->company();
        $account = $this->account($company);
        $this->fakeAudienceList();

        $result = app(MetaAudienceSyncService::class)->sync($account);

        $this->assertSame(['created' => 1, 'updated' => 0], $result);

        $audience = MetaAudience::query()->where('meta_id', 'aud-1')->first();
        $this->assertNotNull($audience);
        $this->assertSame($company->getKey(), $audience->company_id);
        $this->assertSame('Site Visitors', $audience->name);
        $this->assertSame('WEBSITE', $audience->subtype);
        $this->assertSame(1000, $audience->approximate_count_lower_bound);
        $this->assertSame(1500, $audience->approximate_count_upper_bound);
        $this->assertSame('ready', $audience->delivery_status);
        $this->assertNotNull($audience->last_synced_at);
        // Pulled in by sync, never created through this app.
        $this->assertSame(MetaAudience::SOURCE_META, $audience->source);
    }

    public function test_sync_running_twice_updates_the_same_rows_instead_of_duplicating(): void
    {
        $account = $this->account($this->company());
        $this->fakeAudienceList();

        $first = app(MetaAudienceSyncService::class)->sync($account);
        $second = app(MetaAudienceSyncService::class)->sync($account);

        $this->assertSame(['created' => 1, 'updated' => 0], $first);
        $this->assertSame(['created' => 0, 'updated' => 1], $second);
        $this->assertSame(1, MetaAudience::query()->count());
    }

    public function test_sync_never_overwrites_a_locally_created_rows_source_or_erp_only_fields(): void
    {
        $company = $this->company();
        $account = $this->account($company);

        $audience = MetaAudience::query()->create([
            'company_id' => $company->getKey(),
            'meta_ad_account_id' => $account->getKey(),
            'meta_id' => 'aud-1',
            'name' => 'Site Visitors',
            'subtype' => MetaAudience::SUBTYPE_WEBSITE,
            'pixel_id' => 'pixel-1',
            'retention_days' => 30,
            'rule' => ['inclusions' => ['operator' => 'or', 'rules' => []]],
            'source' => MetaAudience::SOURCE_ERP,
        ]);

        $this->fakeAudienceList();
        app(MetaAudienceSyncService::class)->sync($account);

        $audience->refresh();
        $this->assertSame(MetaAudience::SOURCE_ERP, $audience->source);
        $this->assertSame('pixel-1', $audience->pixel_id);
        $this->assertSame(30, $audience->retention_days);
        $this->assertNotNull($audience->rule);
        // But the synced-from-Meta fields still refresh.
        $this->assertSame(1000, $audience->approximate_count_lower_bound);
    }

    public function test_a_failing_sync_throws_instead_of_silently_swallowing(): void
    {
        $account = $this->account($this->company());

        Http::fake([
            'https://graph.facebook.com/*/customaudiences*' => Http::response(['error' => ['message' => 'Invalid OAuth access token.']], 400),
        ]);

        $this->expectException(RequestException::class);
        app(MetaAudienceSyncService::class)->sync($account);
    }

    public function test_sync_is_scoped_per_company(): void
    {
        $companyA = $this->company('meta-audience-co-a', 'Meta Audience Co A', 'MACA');
        $accountA = $this->account($companyA);
        $this->fakeAudienceList();
        app(MetaAudienceSyncService::class)->sync($accountA);

        $companyB = $this->company('meta-audience-co-b', 'Meta Audience Co B', 'MACB');
        app(CompanyContext::class)->set($companyB);

        $this->assertSame(0, MetaAudience::withoutGlobalScopes()->where('company_id', $companyB->getKey())->count());
        $this->assertSame(1, MetaAudience::withoutGlobalScopes()->where('company_id', $companyA->getKey())->count());
    }

    protected function fakeAudienceList(): void
    {
        Http::fake([
            'https://graph.facebook.com/*/act_123456789/customaudiences*' => Http::response(['data' => [
                [
                    'id' => 'aud-1', 'name' => 'Site Visitors', 'subtype' => 'WEBSITE',
                    'approximate_count_lower_bound' => 1000, 'approximate_count_upper_bound' => 1500,
                    'delivery_status' => ['code' => 200, 'description' => 'ready'],
                ],
            ]]),
        ]);
    }

    protected function company(string $slug = 'meta-audience-co', string $name = 'Meta Audience Co', string $invoicePrefix = 'MAC'): Company
    {
        $company = Company::query()->create([
            'name' => $name, 'slug' => $slug,
            'invoice_prefix' => $invoicePrefix, 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
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
