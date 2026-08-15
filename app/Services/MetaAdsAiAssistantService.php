<?php

namespace App\Services;

use App\Models\MetaAd;
use App\Models\MetaAdAccount;
use App\Models\MetaAdCampaign;
use App\Models\MetaAdProposal;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Crm\AiLlmClient;
use App\Services\Crm\AiSettingsService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use stdClass;

/**
 * Phase D: a grounded, tool-calling AI agent (same shape as
 * App\Services\Crm\AiReplyService) that looks at real stock/margin/sales
 * data and this company's own past ad results to propose which product(s)
 * are worth advertising. It NEVER calls Meta itself — every run only ever
 * writes MetaAdProposal rows with status "draft"; the owner reviews and
 * launches through CreateMetaAdCampaign (Phase C), which always creates
 * PAUSED objects, and only Phase B's explicit Resume ever starts spending.
 *
 * Unlike AiReplyService's "every ৳ amount must match a tool result" check
 * (that text is sent straight to a paying customer as a quote/promise),
 * the reasoning text here is internal, owner-facing explanation only. The
 * functional numbers (daily_budget, duration_days, targeting) are instead
 * hard-clamped in code against the account's own configured guardrails,
 * and any product_id the model proposes is rejected outright unless it's
 * one a grounding tool actually returned during this run — no hallucinated
 * products.
 */
class MetaAdsAiAssistantService
{
    /**
     * Lower than AiReplyService's 6 — this runs synchronously inside a
     * user-initiated Filament action, not a queued job, so the worst case
     * must stay well under typical web timeouts.
     */
    protected const MAX_TOOL_ROUNDS = 4;

    protected const ALLOWED_CALL_TO_ACTIONS = ['SHOP_NOW', 'BUY_NOW', 'ORDER_NOW', 'LEARN_MORE'];

    protected const ALLOWED_GENDERS = ['all', 'male', 'female'];

    /** Product IDs a grounding tool actually returned this run — the allow-list submit_recommendation is checked against. */
    protected array $groundedProductIds = [];

    public function __construct(protected AiSettingsService $settings) {}

    /** @return Collection<int, MetaAdProposal> */
    public function generateRecommendations(MetaAdAccount $account): Collection
    {
        if (! $account->aiGuardrailsConfigured()) {
            throw ValidationException::withMessages([
                'account' => 'Set a Max Daily Budget under "AI Marketing Assistant Guardrails" on this ad account before generating recommendations.',
            ]);
        }

        $company = $account->company;
        $settings = $this->settings->all($company);

        if (! $settings['enabled'] || blank($settings['api_key'])) {
            throw ValidationException::withMessages([
                'account' => 'Turn on and configure the AI Assistant (provider + API key) on the AI Assistant Settings page first.',
            ]);
        }

        $this->groundedProductIds = [];

        $client = new AiLlmClient($settings['provider'], $settings['api_key'], $settings['model']);
        $messages = [[
            'role' => 'user',
            'content' => 'Recommend which in-stock product(s) are worth advertising on Meta right now.',
        ]];

        for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
            $response = $client->chat($this->systemPrompt($account), $messages, $this->toolDefinitions());

            if (empty($response['tool_calls'])) {
                throw new RuntimeException('The AI did not produce a structured recommendation.');
            }

            $results = [];

            foreach ($response['tool_calls'] as $call) {
                if ($call['name'] === 'report_no_candidates') {
                    throw ValidationException::withMessages([
                        'account' => 'The AI found nothing worth recommending right now: '.trim((string) ($call['input']['reason'] ?? 'no reason given.')),
                    ]);
                }

                if ($call['name'] === 'submit_recommendation') {
                    return $this->persist($account, $settings, $call['input']);
                }

                $result = $this->executeTool($account, $call['name'], $call['input']);
                $results[] = ['id' => $call['id'], 'result' => $result];
            }

            $this->appendToolExchange($messages, $settings['provider'], $response, $results);
        }

        throw new RuntimeException('The AI could not reach a recommendation within the tool budget.');
    }

    /** @return Collection<int, MetaAdProposal> */
    protected function persist(MetaAdAccount $account, array $settings, array $input): Collection
    {
        $recommended = $this->normalizeProposal($account, (array) ($input['recommended'] ?? []));

        if (! $recommended) {
            throw new RuntimeException('The AI recommendation referenced a product that was never actually looked up — rejected.');
        }

        $comparisonReasoning = trim((string) ($input['comparison_reasoning'] ?? ''));

        if ($comparisonReasoning !== '') {
            $recommended['reasoning_text'] = trim($recommended['reasoning_text']."\n\n".$comparisonReasoning);
        }

        $alternatives = collect((array) ($input['alternatives'] ?? []))
            ->take(2)
            ->map(fn (array $alt): ?array => $this->normalizeProposal($account, $alt))
            ->filter()
            ->values()
            ->all();

        $proposalsData = [
            [...$recommended, 'is_recommended' => true],
            ...array_map(fn (array $alt): array => [...$alt, 'is_recommended' => false], $alternatives),
        ];

        return DB::transaction(function () use ($account, $settings, $proposalsData): Collection {
            // Regenerating clears out this account's stale drafts rather
            // than letting the list accumulate across repeated runs, while
            // keeping them (dismissed, not deleted) as an audit trail.
            MetaAdProposal::query()
                ->where('meta_ad_account_id', $account->getKey())
                ->where('status', MetaAdProposal::STATUS_DRAFT)
                ->update(['status' => MetaAdProposal::STATUS_DISMISSED]);

            return new Collection(collect($proposalsData)->map(fn (array $data): MetaAdProposal => MetaAdProposal::query()->create([
                'company_id' => $account->company_id,
                'meta_ad_account_id' => $account->getKey(),
                'ai_provider' => $settings['provider'],
                'ai_model' => $settings['model'],
                'status' => MetaAdProposal::STATUS_DRAFT,
                ...$data,
            ]))->all());
        });
    }

    /** Null when the product_id isn't one get_candidate_products actually returned this run. */
    protected function normalizeProposal(MetaAdAccount $account, array $raw): ?array
    {
        $productId = (int) ($raw['product_id'] ?? 0);

        if (! in_array($productId, $this->groundedProductIds, true)) {
            return null;
        }

        $product = Product::query()->find($productId);

        $max = (float) $account->ai_daily_budget_max;
        $min = min($account->ai_daily_budget_min !== null ? (float) $account->ai_daily_budget_min : 1.0, $max);
        $dailyBudget = min(max((float) ($raw['daily_budget'] ?? $min), $min), $max);

        $maxDuration = (int) ($account->ai_max_duration_days ?? 30);
        $duration = min(max((int) ($raw['duration_days'] ?? 7), 1), $maxDuration);

        $ageMin = min(max((int) ($raw['age_min'] ?? 18), 13), 65);
        $ageMax = min(max((int) ($raw['age_max'] ?? 55), 13), 65);

        if ($ageMin > $ageMax) {
            [$ageMin, $ageMax] = [$ageMax, $ageMin];
        }

        $gender = in_array($raw['gender'] ?? 'all', self::ALLOWED_GENDERS, true) ? $raw['gender'] : 'all';
        $callToAction = in_array($raw['call_to_action'] ?? '', self::ALLOWED_CALL_TO_ACTIONS, true)
            ? $raw['call_to_action']
            : 'SHOP_NOW';

        $headline = trim((string) ($raw['headline'] ?? ''));
        $primaryText = trim((string) ($raw['primary_text'] ?? ''));

        // Same auto-generated copy CreateMetaAdCampaign's own product-select
        // falls back to — used only when the model left these blank.
        if ($headline === '' && $product) {
            $headline = str($product->name)->limit(40, '')->toString();
        }

        if ($primaryText === '' && $product) {
            $price = number_format((float) ($product->sale_price ?? $product->price), 0);
            $primaryText = "Get {$product->name} now for just ৳{$price}!";
        }

        return [
            'product_id' => $productId,
            'suggested_budget' => round($dailyBudget, 2),
            'suggested_duration_days' => $duration,
            'headline' => $headline,
            'primary_text' => $primaryText,
            'description_text' => filled($raw['description_text'] ?? null) ? trim((string) $raw['description_text']) : null,
            'call_to_action' => $callToAction,
            'targeting' => ['age_min' => $ageMin, 'age_max' => $ageMax, 'gender' => $gender],
            'reasoning_text' => trim((string) ($raw['reasoning'] ?? '')) ?: 'No reasoning provided.',
        ];
    }

    protected function systemPrompt(MetaAdAccount $account): string
    {
        $companyName = $account->company?->name ?? 'the store';

        return <<<PROMPT
You are a marketing analyst for "{$companyName}", a Bangladeshi e-commerce business, deciding which in-stock product(s) are worth advertising on Meta (Facebook/Instagram) right now.

NON-NEGOTIABLE RULES:
1. GROUNDED ONLY: never invent a product, price, margin, stock level, or past ad result. Always call get_candidate_products first, and only ever recommend a product_id that tool actually returned.
2. Call get_product_ad_history and get_account_summary to inform your reasoning with our own real past performance, not general market assumptions.
3. Prefer products with healthy margin AND real recent sales velocity over merely high stock — explain your reasoning in plain, specific terms referencing the real numbers you saw.
4. Your suggested daily_budget and duration_days are a starting point only — the app enforces the account's own configured min/max, so don't worry about exact limits, just propose something reasonable.
5. If nothing in stock is realistically worth advertising (e.g. everything is near-zero margin or dead stock), call report_no_candidates instead of forcing a recommendation.
6. You MUST finish by calling submit_recommendation or report_no_candidates — never answer with plain text.
PROMPT;
    }

    protected function toolDefinitions(): array
    {
        return [
            [
                'name' => 'get_candidate_products',
                'description' => 'Real, in-stock, active products (each with a product image) that could be advertised — includes margin and trailing 30/60-day sales velocity from our own order history.',
                'input_schema' => ['type' => 'object', 'properties' => new stdClass],
            ],
            [
                'name' => 'get_product_ad_history',
                'description' => 'Real past ad performance (spend/impressions/clicks/ctr/cpc) for a specific product, if this company has ever advertised it before.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['product_id' => ['type' => 'integer']],
                    'required' => ['product_id'],
                ],
            ],
            [
                'name' => 'get_account_summary',
                'description' => 'This ad account\'s currency, its own configured budget guardrails, and its real recent account-wide spend/impressions/clicks/ctr/cpc.',
                'input_schema' => ['type' => 'object', 'properties' => new stdClass],
            ],
            [
                'name' => 'submit_recommendation',
                'description' => 'Submit the final recommendation: one top pick plus up to 2 alternatives, each grounded in a product_id returned by get_candidate_products.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'recommended' => $this->proposalSchema(),
                        'alternatives' => ['type' => 'array', 'items' => $this->proposalSchema(), 'maxItems' => 2],
                        'comparison_reasoning' => ['type' => 'string'],
                    ],
                    'required' => ['recommended'],
                ],
            ],
            [
                'name' => 'report_no_candidates',
                'description' => 'Call this instead of submit_recommendation when nothing in stock is realistically worth advertising right now.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['reason' => ['type' => 'string']],
                    'required' => ['reason'],
                ],
            ],
        ];
    }

    protected function proposalSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'product_id' => ['type' => 'integer'],
                'headline' => ['type' => 'string'],
                'primary_text' => ['type' => 'string'],
                'description_text' => ['type' => 'string'],
                'call_to_action' => ['type' => 'string', 'enum' => self::ALLOWED_CALL_TO_ACTIONS],
                'daily_budget' => ['type' => 'number'],
                'duration_days' => ['type' => 'integer'],
                'age_min' => ['type' => 'integer'],
                'age_max' => ['type' => 'integer'],
                'gender' => ['type' => 'string', 'enum' => self::ALLOWED_GENDERS],
                'reasoning' => ['type' => 'string'],
            ],
            'required' => ['product_id', 'headline', 'primary_text', 'call_to_action', 'daily_budget', 'duration_days', 'age_min', 'age_max', 'gender', 'reasoning'],
        ];
    }

    protected function executeTool(MetaAdAccount $account, string $name, array $input): array
    {
        return match ($name) {
            'get_candidate_products' => $this->candidateProducts($account),
            'get_product_ad_history' => $this->productAdHistory($account, (int) ($input['product_id'] ?? 0)),
            'get_account_summary' => $this->accountSummary($account),
            default => ['error' => "Unknown tool {$name}"],
        };
    }

    protected function candidateProducts(MetaAdAccount $account): array
    {
        $products = Product::query()
            ->where('company_id', $account->company_id)
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->whereNotNull('image')
            ->where('image', '!=', '')
            ->orderByDesc('stock')
            ->limit(30)
            ->get(['id', 'name', 'stock', 'reorder_level', 'price', 'sale_price', 'cost_price']);

        if ($products->isEmpty()) {
            return ['found' => false, 'message' => 'No active in-stock products with an image.'];
        }

        $since30 = now()->subDays(30);
        $since60 = now()->subDays(60);

        $sales = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.company_id', $account->company_id)
            ->whereIn('orders.status', Order::ACCOUNTED_STATUSES)
            ->whereIn('order_items.product_id', $products->pluck('id')->all())
            ->where('order_items.created_at', '>=', $since60)
            ->selectRaw(
                'order_items.product_id,'
                .'SUM(CASE WHEN order_items.created_at >= ? THEN order_items.quantity ELSE 0 END) as units_30d,'
                .'SUM(order_items.quantity) as units_60d,'
                .'SUM(CASE WHEN order_items.created_at >= ? THEN order_items.quantity * order_items.unit_price ELSE 0 END) as revenue_30d',
                [$since30, $since30]
            )
            ->groupBy('order_items.product_id')
            ->get()
            ->keyBy('product_id');

        return [
            'found' => true,
            'products' => $products->map(function (Product $product) use ($sales): array {
                $this->groundedProductIds[] = $product->getKey();

                $sellingPrice = (float) ($product->sale_price ?? $product->price);
                $costPrice = (float) $product->cost_price;
                $margin = $sellingPrice > 0 ? round((($sellingPrice - $costPrice) / $sellingPrice) * 100, 1) : 0.0;
                $row = $sales->get($product->getKey());

                return [
                    'id' => $product->getKey(),
                    'name' => $product->name,
                    'stock' => (int) $product->stock,
                    'reorder_level' => (int) $product->reorder_level,
                    'price' => $sellingPrice,
                    'cost_price' => $costPrice,
                    'margin_percent' => $margin,
                    'units_sold_30d' => (int) ($row->units_30d ?? 0),
                    'units_sold_60d' => (int) ($row->units_60d ?? 0),
                    'revenue_30d' => round((float) ($row->revenue_30d ?? 0), 2),
                ];
            })->all(),
        ];
    }

    /**
     * Deliberately Ad-level only (not Ad Set level too) — in this app's own
     * create flow (Phase C) an ad set has exactly one ad and both carry the
     * same product_id, so summing both levels would double-count spend
     * that Meta itself only counts once.
     */
    protected function productAdHistory(MetaAdAccount $account, int $productId): array
    {
        if ($productId <= 0) {
            return ['found' => false, 'message' => 'No product_id given.'];
        }

        $ads = MetaAd::query()->where('company_id', $account->company_id)->where('product_id', $productId)->get();

        if ($ads->isEmpty()) {
            return ['found' => false, 'message' => 'No ad history yet for this product.'];
        }

        $spend = (float) $ads->sum('spend');
        $impressions = (int) $ads->sum('impressions');
        $clicks = (int) $ads->sum('clicks');

        return [
            'found' => true,
            'ad_count' => $ads->count(),
            'spend' => round($spend, 2),
            'impressions' => $impressions,
            'clicks' => $clicks,
            'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0.0,
            'cpc' => $clicks > 0 ? round($spend / $clicks, 4) : 0.0,
        ];
    }

    /** Same aggregate computation MetaAdsDashboard::stats() already does. */
    protected function accountSummary(MetaAdAccount $account): array
    {
        $totals = MetaAdCampaign::query()
            ->where('meta_ad_account_id', $account->getKey())
            ->selectRaw('COALESCE(SUM(spend), 0) as spend, COALESCE(SUM(impressions), 0) as impressions, COALESCE(SUM(clicks), 0) as clicks, COUNT(*) as campaign_count')
            ->first();

        $spend = (float) ($totals->spend ?? 0);
        $impressions = (int) ($totals->impressions ?? 0);
        $clicks = (int) ($totals->clicks ?? 0);

        return [
            'currency' => $account->currency,
            'guardrails' => [
                'min_daily_budget' => $account->ai_daily_budget_min !== null ? (float) $account->ai_daily_budget_min : null,
                'max_daily_budget' => (float) $account->ai_daily_budget_max,
                'max_duration_days' => (int) ($account->ai_max_duration_days ?? 30),
            ],
            'recent_totals' => [
                'campaign_count' => (int) ($totals->campaign_count ?? 0),
                'spend' => round($spend, 2),
                'impressions' => $impressions,
                'clicks' => $clicks,
                'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0.0,
                'cpc' => $clicks > 0 ? round($spend / $clicks, 4) : 0.0,
            ],
        ];
    }

    /** Same normalized-message-append logic as AiReplyService::appendToolExchange. */
    protected function appendToolExchange(array &$messages, string $provider, array $response, array $results): void
    {
        if ($provider === 'openai') {
            $messages[] = [
                'role' => 'assistant',
                'content' => $response['text'],
                'tool_calls' => data_get($response, 'raw_content.tool_calls', []),
            ];

            foreach ($results as $result) {
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $result['id'],
                    'content' => json_encode($result['result']),
                ];
            }

            return;
        }

        $messages[] = ['role' => 'assistant', 'content' => $response['raw_content']];
        $messages[] = [
            'role' => 'user',
            'content' => array_map(fn (array $result): array => [
                'type' => 'tool_result',
                'tool_use_id' => $result['id'],
                'content' => json_encode($result['result']),
            ], $results),
        ];
    }
}
