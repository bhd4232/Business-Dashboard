<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\OfferItem;
use App\Services\Crm\AiLlmClient;
use App\Services\Crm\AiSettingsService;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Drafts an offer landing page's `blocks` JSON via the company's existing AI
 * provider (reused as-is from the CRM auto-reply feature — no new AI
 * settings UI). The LLM only ever writes marketing copy; every price/stock
 * figure shown to the customer comes from the offer's own real data, never
 * from the AI (same "never invent numbers" guardrail as AiReplyService).
 */
class OfferLandingPageAiGenerator
{
    public function __construct(protected AiSettingsService $aiSettings) {}

    public function generate(Offer $offer): array
    {
        $company = $offer->company;
        $settings = $this->aiSettings->all($company);

        if (blank($settings['api_key'] ?? null)) {
            throw new RuntimeException('No AI provider/API key is configured for this company. Set one up on the AI Assistant Settings page first.');
        }

        $client = new AiLlmClient($settings['api_format'], $settings['api_key'], $settings['model'], $settings['base_url'] ?: null);

        $productContext = $offer->items->map(fn (OfferItem $item): array => [
            'name' => $item->product->name,
            'description' => $item->product->description,
            'price' => (float) ($item->productVariant?->effectiveSalePrice() ?? $item->product->selling_price),
        ])->all();

        $blockTypes = implode(', ', array_keys(Offer::BLOCK_TYPES));

        $system = <<<PROMPT
            You are a Bangladeshi e-commerce copywriter. Based on the product information given,
            write a landing page's content in Bengali. Respond with a JSON array only — no prose,
            no markdown code fences, nothing before or after the array.

            Each array element is one block: {"id": "b1", "type": "...", "data": {...}}. Valid
            "type" values are exactly: {$blockTypes}.

            Suggested "data" shapes per type:
            - hero: {"headline", "subheadline", "cta_label"}
            - usp_list: {"heading", "items": [{"icon", "text"}]}
            - how_to_buy: {"heading", "steps": ["...", "..."]}
            - faq: {"heading", "questions": [{"question", "answer"}]}
            - rich_text: {"html"}

            Do not generate "product_gallery" or "testimonials" blocks — those are always filled
            in separately from real product photos and real customer reviews.

            Never invent a price, stock quantity, or delivery number of your own — only use what is
            given in the input. Everything else (headlines, USPs, FAQ questions/answers, "how to
            buy" steps) may be written as normal marketing copy.
            PROMPT;

        $result = $client->chat($system, [
            ['role' => 'user', 'content' => json_encode(['offer_title' => $offer->title, 'products' => $productContext], JSON_UNESCAPED_UNICODE)],
        ], []);

        $blocks = $this->parseBlocksFromResponse($result['text']);

        $offer->update([
            'blocks' => $blocks,
            'is_ai_generated' => true,
            'ai_generated_at' => now(),
        ]);

        return $blocks;
    }

    /**
     * Extracts + validates blocks from the raw LLM text. Unknown/malformed
     * individual blocks are dropped rather than failing the whole
     * generation; a completely unparseable response throws instead of
     * silently saving nothing.
     */
    protected function parseBlocksFromResponse(?string $text): array
    {
        $text = trim((string) $text);
        $text = preg_replace('/^```(?:json)?|```$/m', '', $text) ?? $text;
        $text = trim($text);

        if ($text === '' || ($start = strpos($text, '[')) === false || ($end = strrpos($text, ']')) === false || $end < $start) {
            throw new RuntimeException('The AI did not return usable landing page content. Please try again.');
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('The AI did not return usable landing page content. Please try again.');
        }

        $blocks = collect($decoded)
            ->filter(fn ($block): bool => is_array($block) && array_key_exists('type', $block) && array_key_exists('data', $block) && is_array($block['data']))
            ->filter(fn (array $block): bool => array_key_exists($block['type'], Offer::BLOCK_TYPES))
            ->values()
            ->map(fn (array $block, int $index): array => [
                'id' => filled($block['id'] ?? null) ? (string) $block['id'] : 'b'.($index + 1).'-'.Str::random(4),
                'type' => $block['type'],
                'data' => $block['data'],
            ])
            ->all();

        if ($blocks === []) {
            throw new RuntimeException('The AI response did not contain any recognizable landing page sections. Please try again.');
        }

        return $blocks;
    }
}
