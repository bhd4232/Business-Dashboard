<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\MetaAdAccount;
use App\Models\MetaAdProposal;
use App\Models\Product;
use App\Services\CompanyContext;
use App\Services\Crm\AiSettingsService;
use App\Services\MetaAdsAiAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class MetaAdsAiAssistantServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'AI Ads Co', 'slug' => 'ai-ads-co', 'domain' => 'ai-ads.example.com',
            'invoice_prefix' => 'AAC', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        app(CompanyContext::class)->set($this->company);

        app(AiSettingsService::class)->save($this->company, [
            'enabled' => true,
            'provider' => 'anthropic',
            'model' => 'claude-test',
            'confidence_threshold' => 0.75,
            'max_consecutive_ai_replies' => 3,
            'api_key' => 'test-key',
        ]);
        $this->company->refresh();

        $this->product = Product::query()->create([
            'name' => 'Winter Jacket',
            'sku' => 'AAC-JACKET-001',
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

    protected function account(array $overrides = []): MetaAdAccount
    {
        return MetaAdAccount::query()->create(array_merge([
            'company_id' => $this->company->getKey(),
            'name' => 'Main Account',
            'currency' => 'BDT',
            'credentials' => [
                'app_id' => 'x', 'app_secret' => 'x', 'access_token' => 'x', 'ad_account_id' => '999',
            ],
            'is_active' => true,
            'ai_daily_budget_min' => 50,
            'ai_daily_budget_max' => 200,
            'ai_max_duration_days' => 14,
        ], $overrides));
    }

    /** Fakes an Anthropic tool-call sequence, mirroring AiAutoReplyTest::fakeLlm. */
    protected function fakeLlm(array ...$anthropicResponses): void
    {
        $sequence = Http::sequence();

        foreach ($anthropicResponses as $response) {
            $sequence->push($response);
        }

        Http::fake(['api.anthropic.com/*' => $sequence]);
    }

    protected function anthropicToolUse(string $name, array $input): array
    {
        return [
            'content' => [['type' => 'tool_use', 'id' => 'tu_'.uniqid(), 'name' => $name, 'input' => $input]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
        ];
    }

    public function test_guardrails_not_configured_rejects_before_any_http_call(): void
    {
        Http::fake();
        $account = $this->account(['ai_daily_budget_max' => null]);

        try {
            app(MetaAdsAiAssistantService::class)->generateRecommendations($account);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException) {
            // expected
        }

        Http::assertNothingSent();
    }

    public function test_ai_not_enabled_rejects_before_any_http_call(): void
    {
        Http::fake();
        app(AiSettingsService::class)->save($this->company, ['enabled' => false, 'api_key' => 'test-key']);
        $account = $this->account();

        try {
            app(MetaAdsAiAssistantService::class)->generateRecommendations($account);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException) {
            // expected
        }

        Http::assertNothingSent();
    }

    public function test_happy_path_persists_recommended_with_clamped_budget_and_duration(): void
    {
        $this->fakeLlm(
            $this->anthropicToolUse('get_candidate_products', []),
            $this->anthropicToolUse('submit_recommendation', [
                'recommended' => [
                    'product_id' => $this->product->getKey(),
                    'headline' => 'Winter Jacket Sale',
                    'primary_text' => 'Stay warm this season.',
                    'call_to_action' => 'SHOP_NOW',
                    'daily_budget' => 999, // above the account's max of 200 -> must clamp to 200
                    'duration_days' => 60, // above ai_max_duration_days of 14 -> must clamp to 14
                    'age_min' => 18,
                    'age_max' => 45,
                    'gender' => 'all',
                    'reasoning' => 'Best margin and real 30-day sales velocity.',
                ],
                'comparison_reasoning' => 'Chosen over alternatives due to higher margin.',
            ]),
        );

        $account = $this->account();

        $proposals = app(MetaAdsAiAssistantService::class)->generateRecommendations($account);

        $this->assertCount(1, $proposals);

        $proposal = MetaAdProposal::query()->sole();
        $this->assertSame($account->getKey(), $proposal->meta_ad_account_id);
        $this->assertSame($this->product->getKey(), $proposal->product_id);
        $this->assertTrue($proposal->is_recommended);
        $this->assertSame('200.00', $proposal->suggested_budget);
        $this->assertSame(14, $proposal->suggested_duration_days);
        $this->assertSame(MetaAdProposal::STATUS_DRAFT, $proposal->status);
        $this->assertSame('anthropic', $proposal->ai_provider);
        $this->assertStringContainsString('Chosen over alternatives', $proposal->reasoning_text);
    }

    public function test_hallucinated_product_id_is_rejected_with_no_rows_created(): void
    {
        $this->fakeLlm(
            $this->anthropicToolUse('get_candidate_products', []),
            $this->anthropicToolUse('submit_recommendation', [
                'recommended' => [
                    'product_id' => 999999, // never returned by get_candidate_products
                    'headline' => 'Ghost Product',
                    'primary_text' => 'Too good to be true.',
                    'call_to_action' => 'SHOP_NOW',
                    'daily_budget' => 100,
                    'duration_days' => 7,
                    'age_min' => 18,
                    'age_max' => 45,
                    'gender' => 'all',
                    'reasoning' => 'Fabricated.',
                ],
            ]),
        );

        $account = $this->account();

        try {
            app(MetaAdsAiAssistantService::class)->generateRecommendations($account);
            $this->fail('Expected a RuntimeException.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(0, MetaAdProposal::query()->count());
    }

    public function test_regenerating_dismisses_prior_drafts(): void
    {
        $account = $this->account();

        $existing = MetaAdProposal::query()->create([
            'company_id' => $this->company->getKey(),
            'meta_ad_account_id' => $account->getKey(),
            'product_id' => $this->product->getKey(),
            'is_recommended' => true,
            'suggested_budget' => 60,
            'status' => MetaAdProposal::STATUS_DRAFT,
        ]);

        $this->fakeLlm(
            $this->anthropicToolUse('get_candidate_products', []),
            $this->anthropicToolUse('submit_recommendation', [
                'recommended' => [
                    'product_id' => $this->product->getKey(),
                    'headline' => 'Winter Jacket Sale',
                    'primary_text' => 'Stay warm this season.',
                    'call_to_action' => 'SHOP_NOW',
                    'daily_budget' => 100,
                    'duration_days' => 7,
                    'age_min' => 18,
                    'age_max' => 45,
                    'gender' => 'all',
                    'reasoning' => 'Best pick.',
                ],
            ]),
        );

        app(MetaAdsAiAssistantService::class)->generateRecommendations($account);

        $this->assertSame(MetaAdProposal::STATUS_DISMISSED, $existing->fresh()->status);
        $this->assertSame(1, MetaAdProposal::query()->where('status', MetaAdProposal::STATUS_DRAFT)->count());
    }

    public function test_report_no_candidates_rejects_without_creating_rows(): void
    {
        $this->fakeLlm(
            $this->anthropicToolUse('get_candidate_products', []),
            $this->anthropicToolUse('report_no_candidates', ['reason' => 'Everything is near-zero margin.']),
        );

        $account = $this->account();

        try {
            app(MetaAdsAiAssistantService::class)->generateRecommendations($account);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('near-zero margin', collect($exception->errors())->flatten()->implode(' '));
        }

        $this->assertSame(0, MetaAdProposal::query()->count());
    }
}
