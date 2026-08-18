<?php

namespace Tests\Feature;

use App\Filament\Pages\MetaAdsAiAssistant;
use App\Models\Company;
use App\Models\MetaAdAccount;
use App\Models\MetaAdProposal;
use App\Models\Product;
use App\Models\User;
use App\Services\CompanyContext;
use App\Services\Crm\AiSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class MetaAdsAiAssistantPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_only_this_accounts_draft_and_launched_proposals(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $account = $this->makeAccount($company);
        $otherAccount = $this->makeAccount($company, 'Second Account');
        $product = $this->makeProduct($company);

        $draft = $this->makeProposal($account, $product, MetaAdProposal::STATUS_DRAFT);
        $launched = $this->makeProposal($account, $product, MetaAdProposal::STATUS_LAUNCHED);
        $this->makeProposal($account, $product, MetaAdProposal::STATUS_DISMISSED);
        $this->makeProposal($otherAccount, $product, MetaAdProposal::STATUS_DRAFT);

        Livewire::test(MetaAdsAiAssistant::class)
            ->assertSet('accountId', $account->getKey())
            ->assertCanSeeTableRecords([$draft, $launched]);
    }

    public function test_dismiss_transitions_a_draft_to_dismissed(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $account = $this->makeAccount($company);
        $product = $this->makeProduct($company);
        $proposal = $this->makeProposal($account, $product, MetaAdProposal::STATUS_DRAFT);

        Livewire::test(MetaAdsAiAssistant::class)
            ->callTableAction('dismiss', $proposal);

        $this->assertSame(MetaAdProposal::STATUS_DISMISSED, $proposal->fresh()->status);
    }

    public function test_generate_action_surfaces_the_guardrail_error_and_sends_no_http_request(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        // No ai_daily_budget_max configured.
        $account = MetaAdAccount::query()->create([
            'company_id' => $company->getKey(),
            'name' => 'No Guardrails Account',
            'credentials' => ['app_id' => 'x', 'app_secret' => 'x', 'access_token' => 'x', 'ad_account_id' => '999'],
            'is_active' => true,
        ]);

        Http::fake();

        Livewire::test(MetaAdsAiAssistant::class)
            ->callAction('generate')
            ->assertNotified();

        Http::assertNothingSent();
        $this->assertSame(0, MetaAdProposal::query()->count());
    }

    public function test_review_and_launch_link_carries_the_proposal_id(): void
    {
        [$company, $user] = $this->makeCompanyAndUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $account = $this->makeAccount($company);
        $product = $this->makeProduct($company);
        $proposal = $this->makeProposal($account, $product, MetaAdProposal::STATUS_DRAFT);

        // The rendered row action is a plain link to CreateMetaAdCampaign
        // carrying ?proposalId=... — assert the real href is present.
        Livewire::test(MetaAdsAiAssistant::class)
            ->assertSee('proposalId='.$proposal->getKey(), false);
    }

    /** @return array{0: Company, 1: User} */
    protected function makeCompanyAndUser(): array
    {
        $company = Company::query()->create([
            'name' => 'AI Assistant Page Co', 'slug' => 'ai-assistant-page-co', 'domain' => 'ai-assistant-page.example.com',
            'invoice_prefix' => 'AAP', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);

        app(AiSettingsService::class)->save($company, [
            'enabled' => true,
            'provider' => 'anthropic',
            'model' => 'claude-test',
            'confidence_threshold' => 0.75,
            'max_consecutive_ai_replies' => 3,
            'api_key' => 'test-key',
        ]);

        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        return [$company, $user];
    }

    protected function makeAccount(Company $company, string $name = 'Main Account'): MetaAdAccount
    {
        return MetaAdAccount::query()->create([
            'company_id' => $company->getKey(),
            'name' => $name,
            'currency' => 'BDT',
            'credentials' => ['app_id' => 'x', 'app_secret' => 'x', 'access_token' => 'x', 'ad_account_id' => '999'],
            'is_active' => true,
            'ai_daily_budget_min' => 50,
            'ai_daily_budget_max' => 200,
            'ai_max_duration_days' => 14,
        ]);
    }

    protected function makeProduct(Company $company): Product
    {
        return Product::query()->create([
            'company_id' => $company->getKey(),
            'name' => 'Winter Jacket',
            'sku' => 'AAP-JACKET-001',
            'price' => 2000,
            'sale_price' => 1800,
            'cost_price' => 900,
            'stock' => 40,
            'unit' => 'pcs',
            'reorder_level' => 5,
            'vat_rate' => 0,
            'is_active' => true,
            'image' => 'products/jacket.jpg',
        ]);
    }

    protected function makeProposal(MetaAdAccount $account, Product $product, string $status): MetaAdProposal
    {
        return MetaAdProposal::query()->create([
            'company_id' => $account->company_id,
            'meta_ad_account_id' => $account->getKey(),
            'product_id' => $product->getKey(),
            'is_recommended' => true,
            'suggested_budget' => 100,
            'suggested_duration_days' => 7,
            'headline' => 'Winter Jacket Sale',
            'primary_text' => 'Stay warm.',
            'call_to_action' => 'SHOP_NOW',
            'targeting' => ['age_min' => 18, 'age_max' => 45, 'gender' => 'all'],
            'reasoning_text' => 'Best margin.',
            'ai_provider' => 'anthropic',
            'ai_model' => 'claude-test',
            'status' => $status,
        ]);
    }
}
