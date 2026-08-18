<?php

namespace Tests\Feature;

use App\Filament\Pages\MetaAdsDashboard;
use App\Models\Company;
use App\Models\MetaAd;
use App\Models\MetaAdAccount;
use App\Models\MetaAdCampaign;
use App\Models\MetaAdSet;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class MetaAdsDashboardPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_defaults_to_the_only_active_account_and_lists_its_campaigns(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $account = $this->makeAccount($company);
        $campaign = $this->makeCampaign($account, 'Eid Sale');

        Livewire::test(MetaAdsDashboard::class)
            ->assertSet('accountId', $account->getKey())
            ->assertCanSeeTableRecords([$campaign]);
    }

    public function test_drilling_into_a_campaign_then_an_ad_set_narrows_the_table(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $account = $this->makeAccount($company);
        $campaign = $this->makeCampaign($account, 'Eid Sale');
        $otherCampaign = $this->makeCampaign($account, 'Winter Sale');
        $adSet = $this->makeAdSet($campaign, 'Broad Audience');
        $ad = $this->makeAd($adSet, 'Carousel Ad');

        $component = Livewire::test(MetaAdsDashboard::class)
            ->assertCanSeeTableRecords([$campaign, $otherCampaign])
            ->call('drillInto', $campaign->getKey())
            ->assertSet('campaignId', $campaign->getKey())
            ->assertCanSeeTableRecords([$adSet]);

        $component
            ->call('drillInto', $adSet->getKey())
            ->assertSet('adSetId', $adSet->getKey())
            ->assertCanSeeTableRecords([$ad]);

        $component
            ->call('drillBackToCampaigns')
            ->assertSet('campaignId', null)
            ->assertSet('adSetId', null)
            ->assertCanSeeTableRecords([$campaign, $otherCampaign]);
    }

    public function test_an_account_from_another_company_is_not_visible(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();

        $otherCompany = Company::query()->create([
            'name' => 'Other Ads Co', 'slug' => 'other-ads-co',
            'invoice_prefix' => 'OAC', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        app(CompanyContext::class)->set($otherCompany);
        $otherAccount = $this->makeAccount($otherCompany);
        $this->makeCampaign($otherAccount, 'Other Co Campaign');

        app(CompanyContext::class)->set($company);
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $component = Livewire::test(MetaAdsDashboard::class);

        $this->assertNull($component->get('accountId'));
        $this->assertCount(0, $component->instance()->accounts());
    }

    public function test_sync_data_action_pulls_from_meta_and_persists_locally(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $account = $this->makeAccount($company);

        Http::fake([
            'https://graph.facebook.com/*/act_123456789/campaigns*' => Http::response(['data' => [
                ['id' => '1001', 'name' => 'Synced Campaign', 'status' => 'ACTIVE', 'insights' => ['data' => [['spend' => '9.99', 'impressions' => '500', 'clicks' => '10']]]],
            ]]),
            'https://graph.facebook.com/*/1001/adsets*' => Http::response(['data' => []]),
        ]);

        Livewire::test(MetaAdsDashboard::class)
            ->callAction('syncData')
            ->assertNotified();

        $this->assertDatabaseHas('meta_ad_campaigns', [
            'meta_ad_account_id' => $account->getKey(),
            'meta_id' => '1001',
            'name' => 'Synced Campaign',
            'spend' => 9.99,
        ]);
    }

    public function test_pause_and_resume_write_to_meta_first_and_only_update_locally_on_success(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $account = $this->makeAccount($company);
        $campaign = $this->makeCampaign($account, 'Eid Sale');

        Http::fake([
            'https://graph.facebook.com/*/'.$campaign->meta_id => Http::response(['success' => true]),
        ]);

        Livewire::test(MetaAdsDashboard::class)
            ->callTableAction('toggleStatus', $campaign)
            ->assertNotified();

        $this->assertSame('PAUSED', $campaign->refresh()->status);

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && str_ends_with((string) $request->url(), $campaign->meta_id)
            && $request['status'] === 'PAUSED');

        // Resume flips it back.
        Livewire::test(MetaAdsDashboard::class)
            ->callTableAction('toggleStatus', $campaign->fresh())
            ->assertNotified();

        $this->assertSame('ACTIVE', $campaign->refresh()->status);
    }

    public function test_a_failed_status_update_leaves_the_local_row_unchanged(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $account = $this->makeAccount($company);
        $campaign = $this->makeCampaign($account, 'Eid Sale');

        Http::fake([
            'https://graph.facebook.com/*/'.$campaign->meta_id => Http::response(['error' => ['message' => 'Invalid parameter']], 400),
        ]);

        Livewire::test(MetaAdsDashboard::class)
            ->callTableAction('toggleStatus', $campaign)
            ->assertNotified();

        $this->assertSame('ACTIVE', $campaign->refresh()->status);
    }

    public function test_editing_daily_budget_writes_to_meta_in_minor_units_and_persists_locally_on_success(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $account = $this->makeAccount($company);
        $campaign = $this->makeCampaign($account, 'Eid Sale');

        Http::fake([
            'https://graph.facebook.com/*/'.$campaign->meta_id => Http::response(['success' => true]),
        ]);

        Livewire::test(MetaAdsDashboard::class)
            ->assertTableColumnStateSet('daily_budget', null, $campaign)
            ->call('updateTableColumnState', 'daily_budget', (string) $campaign->getKey(), '30.50');

        $this->assertSame('30.50', $campaign->refresh()->daily_budget);

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && str_ends_with((string) $request->url(), $campaign->meta_id)
            && (int) $request['daily_budget'] === 3050);
    }

    public function test_a_failed_budget_update_leaves_the_local_row_unchanged(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $account = $this->makeAccount($company);
        $campaign = $this->makeCampaign($account, 'Eid Sale');

        Http::fake([
            'https://graph.facebook.com/*/'.$campaign->meta_id => Http::response(['error' => ['message' => 'Budget too low']], 400),
        ]);

        Livewire::test(MetaAdsDashboard::class)
            ->call('updateTableColumnState', 'daily_budget', (string) $campaign->getKey(), '5.00');

        $this->assertNull($campaign->refresh()->daily_budget);
    }

    public function test_daily_budget_is_read_only_at_the_ad_level(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $account = $this->makeAccount($company);
        $campaign = $this->makeCampaign($account, 'Eid Sale');
        $adSet = $this->makeAdSet($campaign, 'Broad Audience');
        $ad = $this->makeAd($adSet, 'Carousel Ad');

        Http::fake();

        // At the Ad level, "updateTableColumnState" for daily_budget should
        // not exist as an editable column at all — the ad table only ever
        // shows it read-only, so there is nothing to write through.
        Livewire::test(MetaAdsDashboard::class)
            ->set('campaignId', $campaign->getKey())
            ->set('adSetId', $adSet->getKey())
            ->assertCanSeeTableRecords([$ad]);

        Http::assertNothingSent();
    }

    /** @return array{0: Company, 1: User} */
    protected function makeCompanyAndUser(): array
    {
        $company = Company::query()->create([
            'name' => 'Ads Dashboard Co', 'slug' => 'ads-dashboard-co',
            'invoice_prefix' => 'ADC', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);

        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        return [$company, $user];
    }

    protected function makeAccount(Company $company): MetaAdAccount
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

    protected function makeCampaign(MetaAdAccount $account, string $name): MetaAdCampaign
    {
        return MetaAdCampaign::query()->create([
            'company_id' => $account->company_id,
            'meta_ad_account_id' => $account->getKey(),
            'meta_id' => (string) random_int(100000, 999999),
            'name' => $name,
            'status' => 'ACTIVE',
        ]);
    }

    protected function makeAdSet(MetaAdCampaign $campaign, string $name): MetaAdSet
    {
        return MetaAdSet::query()->create([
            'company_id' => $campaign->company_id,
            'meta_ad_campaign_id' => $campaign->getKey(),
            'meta_id' => (string) random_int(100000, 999999),
            'name' => $name,
            'status' => 'ACTIVE',
        ]);
    }

    protected function makeAd(MetaAdSet $adSet, string $name): MetaAd
    {
        return MetaAd::query()->create([
            'company_id' => $adSet->company_id,
            'meta_ad_set_id' => $adSet->getKey(),
            'meta_id' => (string) random_int(100000, 999999),
            'name' => $name,
            'status' => 'ACTIVE',
        ]);
    }
}
