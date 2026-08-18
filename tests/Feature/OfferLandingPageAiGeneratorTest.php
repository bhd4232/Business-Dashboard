<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Offer;
use App\Models\OfferItem;
use App\Models\Product;
use App\Services\CompanyContext;
use App\Services\Crm\AiSettingsService;
use App\Services\OfferLandingPageAiGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OfferLandingPageAiGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Offer $offer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'AI Landing Co',
            'slug' => 'ai-landing-co',
            'invoice_prefix' => 'AILC',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        app(CompanyContext::class)->set($this->company);

        app(AiSettingsService::class)->save($this->company, [
            'enabled' => true,
            'provider' => 'anthropic',
            'model' => 'claude-test',
            'api_key' => 'test-key',
        ]);
        $this->company->refresh();

        $product = Product::query()->create([
            'name' => 'Winter Jacket',
            'sku' => 'AI-JACKET-001',
            'price' => 2000,
            'sale_price' => 1800,
            'cost_price' => 900,
            'stock' => 10,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);

        $this->offer = Offer::query()->create([
            'company_id' => $this->company->getKey(),
            'type' => Offer::TYPE_SINGLE,
            'title' => 'Winter Jacket Deal',
            'status' => Offer::STATUS_PUBLISHED,
            'price_mode' => Offer::PRICE_MODE_AUTO_SUM,
        ]);

        OfferItem::query()->create([
            'company_id' => $this->company->getKey(),
            'offer_id' => $this->offer->getKey(),
            'product_id' => $product->getKey(),
            'quantity' => 1,
        ]);

        $this->offer->refresh()->load('items.product');
    }

    public function test_generates_and_persists_blocks_from_a_valid_ai_response(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response($this->anthropicText(json_encode([
                ['id' => 'b1', 'type' => 'hero', 'data' => ['headline' => 'শীতের সেরা অফার']],
                ['id' => 'b2', 'type' => 'faq', 'data' => ['questions' => [['question' => 'ডেলিভারি কতদিনে?', 'answer' => '৩-৫ দিন']]]],
            ], JSON_UNESCAPED_UNICODE))),
        ]);

        $blocks = app(OfferLandingPageAiGenerator::class)->generate($this->offer);

        $this->assertCount(2, $blocks);
        $this->assertSame('hero', $blocks[0]['type']);
        $this->offer->refresh();
        $this->assertTrue((bool) $this->offer->is_ai_generated);
        $this->assertNotNull($this->offer->ai_generated_at);
        $this->assertSame('শীতের সেরা অফার', $this->offer->blocks[0]['data']['headline']);
    }

    public function test_unknown_block_types_are_dropped_but_valid_ones_survive(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response($this->anthropicText(json_encode([
                ['id' => 'b1', 'type' => 'not_a_real_block', 'data' => ['x' => 1]],
                ['id' => 'b2', 'type' => 'rich_text', 'data' => ['html' => '<p>Hello</p>']],
            ]))),
        ]);

        $blocks = app(OfferLandingPageAiGenerator::class)->generate($this->offer);

        $this->assertCount(1, $blocks);
        $this->assertSame('rich_text', $blocks[0]['type']);
    }

    public function test_malformed_json_response_throws_without_saving_anything(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response($this->anthropicText('this is not JSON at all')),
        ]);

        $originalBlocks = $this->offer->blocks;

        try {
            app(OfferLandingPageAiGenerator::class)->generate($this->offer);
            $this->fail('Expected a RuntimeException for malformed AI output.');
        } catch (RuntimeException) {
            // expected
        }

        $this->offer->refresh();
        $this->assertSame($originalBlocks, $this->offer->blocks);
        $this->assertFalse((bool) $this->offer->is_ai_generated);
    }

    public function test_missing_api_key_throws_before_any_http_call(): void
    {
        Http::fake();

        $settings = (array) $this->company->settings;
        $settings['ai']['api_key'] = null;
        $this->company->forceFill(['settings' => $settings])->save();
        $this->company->refresh();

        try {
            app(OfferLandingPageAiGenerator::class)->generate($this->offer);
            $this->fail('Expected a RuntimeException for a missing API key.');
        } catch (RuntimeException) {
            // expected
        }

        Http::assertNothingSent();
    }

    protected function anthropicText(string $text): array
    {
        return [
            'content' => [['type' => 'text', 'text' => $text]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
        ];
    }
}
