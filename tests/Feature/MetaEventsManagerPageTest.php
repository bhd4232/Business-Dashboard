<?php

namespace Tests\Feature;

use App\Filament\Pages\MetaEventsManager;
use App\Models\Company;
use App\Models\MetaAdAccount;
use App\Models\MetaAudience;
use App\Models\StorefrontSetting;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class MetaEventsManagerPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_defaults_to_the_only_active_account_and_its_first_configured_pixel(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $this->makePixelSetting($company, '123456789012345');
        $account = $this->makeAccount($company);

        Livewire::test(MetaEventsManager::class)
            ->assertSet('accountId', $account->getKey())
            ->assertSet('pixelId', '123456789012345');
    }

    public function test_the_audiences_table_is_scoped_to_the_selected_account_and_company(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $account = $this->makeAccount($company);
        $otherAccount = $this->makeAccount($company, 'Second Account');

        $mine = $this->makeAudience($account, 'aud-mine');
        $other = $this->makeAudience($otherAccount, 'aud-other');

        Livewire::test(MetaEventsManager::class)
            ->assertSet('accountId', $account->getKey())
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_new_audience_modal_website_type_creates_a_real_row_locally(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $this->makePixelSetting($company, '123456789012345');
        $account = $this->makeAccount($company);

        Http::fake([
            'https://graph.facebook.com/*/act_999/customaudiences' => Http::response(['id' => 'aud-new-1']),
        ]);

        Livewire::test(MetaEventsManager::class)
            ->callAction('newAudience', [
                'type' => MetaAudience::SUBTYPE_WEBSITE,
                'name' => 'Site Visitors 30d',
                'pixel_id' => '123456789012345',
                'retention_days' => 30,
                'url_contains' => null,
            ])
            ->assertHasNoActionErrors();

        $audience = MetaAudience::query()->where('meta_id', 'aud-new-1')->first();
        $this->assertNotNull($audience);
        $this->assertSame($account->getKey(), $audience->meta_ad_account_id);
        $this->assertSame(MetaAudience::SUBTYPE_WEBSITE, $audience->subtype);
        $this->assertSame('123456789012345', $audience->pixel_id);
        $this->assertSame(30, $audience->retention_days);
        $this->assertNotNull($audience->rule);
        $this->assertSame(MetaAudience::SOURCE_ERP, $audience->source);
    }

    public function test_new_audience_modal_lookalike_type_only_offers_this_apps_own_website_audiences_as_origin(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $account = $this->makeAccount($company);
        $websiteAudience = $this->makeAudience($account, 'aud-website', MetaAudience::SUBTYPE_WEBSITE, 'Website Visitors');
        $this->makeAudience($account, 'aud-lookalike-existing', MetaAudience::SUBTYPE_LOOKALIKE, 'Existing Lookalike');

        $modalHtml = Livewire::test(MetaEventsManager::class)
            ->mountAction('newAudience')
            ->getMountedActionModalHtml();

        $this->assertStringContainsString('Website Visitors', $modalHtml);
        $this->assertStringNotContainsString('Existing Lookalike', $modalHtml);
    }

    public function test_delete_calls_meta_first_and_only_removes_the_local_row_on_success(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $account = $this->makeAccount($company);
        $audience = $this->makeAudience($account, 'aud-to-delete');

        Http::fake([
            'https://graph.facebook.com/*/aud-to-delete' => Http::response(['success' => true]),
        ]);

        Livewire::test(MetaEventsManager::class)
            ->callTableAction('delete', $audience);

        $this->assertNull(MetaAudience::query()->find($audience->getKey()));
    }

    public function test_delete_leaves_the_local_row_when_meta_rejects_the_call(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $account = $this->makeAccount($company);
        $audience = $this->makeAudience($account, 'aud-protected');

        Http::fake([
            'https://graph.facebook.com/*/aud-protected' => Http::response(['error' => ['message' => 'Permission denied']], 400),
        ]);

        Livewire::test(MetaEventsManager::class)
            ->callTableAction('delete', $audience)
            ->assertNotified();

        $this->assertNotNull(MetaAudience::query()->find($audience->getKey()));
    }

    public function test_pixel_health_panel_does_not_crash_when_stats_returns_an_unrecognized_shape(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $this->makePixelSetting($company, '123456789012345');
        $this->makeAccount($company);

        Http::fake([
            'https://graph.facebook.com/*/123456789012345/stats*' => Http::response([
                'data' => [['unexpected_key' => 'unexpected_value']],
            ]),
            'https://graph.facebook.com/*/123456789012345*' => Http::response([
                'id' => '123456789012345', 'name' => 'Main Pixel', 'is_unavailable' => false,
            ]),
        ]);

        $component = Livewire::test(MetaEventsManager::class);

        $component->assertOk();
        $this->assertNull($component->get('pixelEventStatsRows'));
        $this->assertNotNull($component->get('pixelStatsNotice'));
    }

    public function test_no_pixel_configured_shows_the_capi_settings_link_without_blocking_the_audiences_table(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $account = $this->makeAccount($company);
        $audience = $this->makeAudience($account, 'aud-visible-anyway');

        Http::fake();

        Livewire::test(MetaEventsManager::class)
            ->assertSet('pixelId', null)
            ->assertCanSeeTableRecords([$audience]);

        Http::assertNothingSent();
    }

    /** @return array{0: Company, 1: User} */
    protected function makeCompanyAndUser(): array
    {
        $company = Company::query()->create([
            'name' => 'Events Manager Page Co', 'slug' => 'events-manager-page-co', 'domain' => 'events-manager-page.example.com',
            'invoice_prefix' => 'EMP', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);

        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        return [$company, $user];
    }

    protected function makePixelSetting(Company $company, string $pixelId): StorefrontSetting
    {
        return StorefrontSetting::query()->create([
            'company_id' => $company->getKey(),
            'theme_color' => '#0F766E',
            'meta_title' => $company->name,
            'is_published' => true,
            'new_customer_delivery_advance_enabled' => false,
            'meta_pixel_id' => $pixelId,
            'meta_tracking_credentials' => ['access_token' => 'capi-token'],
        ]);
    }

    protected function makeAccount(Company $company, string $name = 'Main Account'): MetaAdAccount
    {
        return MetaAdAccount::query()->create([
            'company_id' => $company->getKey(),
            'name' => $name,
            'currency' => 'BDT',
            'credentials' => ['app_id' => 'x', 'app_secret' => 'x', 'access_token' => 'x', 'ad_account_id' => '999'],
            'is_active' => true,
        ]);
    }

    protected function makeAudience(MetaAdAccount $account, string $metaId, string $subtype = MetaAudience::SUBTYPE_WEBSITE, string $name = 'Test Audience'): MetaAudience
    {
        return MetaAudience::query()->create([
            'company_id' => $account->company_id,
            'meta_ad_account_id' => $account->getKey(),
            'meta_id' => $metaId,
            'name' => $name,
            'subtype' => $subtype,
        ]);
    }
}
