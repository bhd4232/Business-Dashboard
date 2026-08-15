<?php

namespace Tests\Feature;

use App\Filament\Resources\MetaAdAccounts\Pages\EditMetaAdAccount;
use App\Models\Company;
use App\Models\MetaAdAccount;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MetaAdAccountResourceTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'Ad Account Resource Co', 'slug' => 'ad-account-resource-co',
            'invoice_prefix' => 'AARC', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        app(CompanyContext::class)->set($this->company);

        $this->actingAs(User::factory()->create(['role' => 'super_admin', 'is_active' => true]));
    }

    /**
     * Regression test: `MetaAdAccount::$hidden` excludes `credentials` from
     * Filament's default edit-form fill, which — without
     * EditMetaAdAccount's mutateFormDataBeforeFill()/mutateFormDataBeforeSave()
     * override — left App Secret/Access Token blank on every edit and
     * wiped the real encrypted values on save.
     */
    public function test_editing_with_blank_secret_fields_preserves_encrypted_credentials(): void
    {
        $account = MetaAdAccount::query()->create([
            'company_id' => $this->company->getKey(),
            'name' => 'Secure Account',
            'credentials' => [
                'app_id' => 'real-app-id',
                'app_secret' => 'real-app-secret',
                'access_token' => 'real-access-token',
                'ad_account_id' => '123456789',
                'page_id' => '999888777',
            ],
            'is_active' => true,
        ]);

        Livewire::test(EditMetaAdAccount::class, ['record' => $account->getRouteKey()])
            ->assertFormSet([
                'credentials.app_id' => 'real-app-id',
                'credentials.app_secret' => null,
                'credentials.access_token' => null,
                'credentials.ad_account_id' => '123456789',
                'credentials.page_id' => '999888777',
            ])
            ->fillForm([
                'name' => 'Renamed Secure Account',
                'credentials.app_secret' => '',
                'credentials.access_token' => '',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $account->refresh();
        $this->assertSame('Renamed Secure Account', $account->name);
        $this->assertSame('real-app-secret', $account->appSecret());
        $this->assertSame('real-access-token', $account->accessToken());
        $this->assertSame('123456789', $account->adAccountId());
    }

    public function test_typing_new_secret_values_overwrites_the_existing_ones(): void
    {
        $account = MetaAdAccount::query()->create([
            'company_id' => $this->company->getKey(),
            'name' => 'Rotating Account',
            'credentials' => [
                'app_id' => 'old-app-id',
                'app_secret' => 'old-app-secret',
                'access_token' => 'old-access-token',
                'ad_account_id' => '111',
            ],
            'is_active' => true,
        ]);

        Livewire::test(EditMetaAdAccount::class, ['record' => $account->getRouteKey()])
            ->fillForm([
                'credentials.app_secret' => 'new-app-secret',
                'credentials.access_token' => 'new-access-token',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $account->refresh();
        $this->assertSame('new-app-secret', $account->appSecret());
        $this->assertSame('new-access-token', $account->accessToken());
    }

    /**
     * Regression test: Laravel's built-in `gte:field` rule fails outright
     * whenever the compared field is blank (it cannot treat blank as "no
     * floor"), but Min Daily Budget is genuinely optional — setting only
     * Max Daily Budget must be allowed.
     */
    public function test_max_daily_budget_can_be_set_alone_without_a_min(): void
    {
        $account = MetaAdAccount::query()->create([
            'company_id' => $this->company->getKey(),
            'name' => 'Budget Only Max',
            'credentials' => ['app_id' => 'x', 'app_secret' => 'x', 'access_token' => 'x', 'ad_account_id' => '111'],
            'is_active' => true,
        ]);

        Livewire::test(EditMetaAdAccount::class, ['record' => $account->getRouteKey()])
            ->fillForm(['ai_daily_budget_max' => 50])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('50.00', $account->refresh()->ai_daily_budget_max);
    }

    public function test_max_daily_budget_below_min_is_rejected(): void
    {
        $account = MetaAdAccount::query()->create([
            'company_id' => $this->company->getKey(),
            'name' => 'Budget Min Max',
            'credentials' => ['app_id' => 'x', 'app_secret' => 'x', 'access_token' => 'x', 'ad_account_id' => '111'],
            'is_active' => true,
        ]);

        Livewire::test(EditMetaAdAccount::class, ['record' => $account->getRouteKey()])
            ->fillForm(['ai_daily_budget_min' => 100, 'ai_daily_budget_max' => 50])
            ->call('save')
            ->assertHasFormErrors(['ai_daily_budget_max']);

        $this->assertNull($account->refresh()->ai_daily_budget_max);
    }
}
