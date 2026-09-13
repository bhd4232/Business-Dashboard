<?php

namespace App\Services\Crm;

use App\Models\Company;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Per-company, per-tool AI settings, stored in the existing `companies.settings`
 * json column under `ai_tools.{tool}`. Each of the tools below can be pointed at
 * its own provider/model/API key — a fast cheap model for chat replies, a
 * stronger reasoning model for ad strategy, a creative-writing model for
 * landing-page copy, etc. — instead of sharing one global config. The API key
 * is encrypted at rest — admin-configurable, never hardcoded (CLAUDE.md rule).
 *
 * `provider` is a free-text label only (any name the admin wants — "OpenAI",
 * "DeepSeek", "Groq", "OpenRouter", "My local Ollama", ...). What actually
 * decides how the HTTP request is shaped is `api_format`: 'anthropic' (the
 * Messages API) or 'openai' (the Chat Completions shape, which nearly every
 * non-Anthropic LLM provider today speaks — OpenAI itself, DeepSeek, Groq,
 * Mistral, OpenRouter, xAI, self-hosted Ollama/vLLM, etc.). `base_url` lets
 * the admin point that request at any endpoint; left blank it defaults to
 * that format's own official endpoint. This is how any new provider gets
 * added without a code change: pick "OpenAI-compatible", paste that
 * provider's own base URL, model name, and API key.
 *
 * Migration from the pre-per-tool single global config (`settings->ai`): when
 * a given tool has never been saved under `ai_tools.{tool}`, all() falls back
 * (in-memory only, nothing written back) to that legacy blob, so every tool
 * reads the same config that used to be shared until an admin explicitly
 * saves that tool through the new per-tool UI — at which point only that
 * tool's own entry is used going forward.
 */
class AiSettingsService
{
    public const TOOL_MESSAGING = 'messaging';

    public const TOOL_AD_ASSISTANT = 'ad_assistant';

    public const TOOL_LANDING_PAGE = 'landing_page';

    /** @var array<string, string> */
    public const TOOLS = [
        self::TOOL_MESSAGING => 'Auto Messaging (Inbox AI Reply)',
        self::TOOL_AD_ASSISTANT => 'Ad Assistant (Meta Ads)',
        self::TOOL_LANDING_PAGE => 'Landing Page Builder (Offer Pages)',
    ];

    public const DEFAULTS = [
        'enabled' => false,
        'provider' => 'Anthropic (Claude)', // free-text label, shown in the UI/audit trail only
        'api_format' => 'anthropic', // anthropic | openai — the actual request/response wire format
        'base_url' => '', // optional endpoint override; blank = api_format's own default endpoint
        'model' => 'claude-haiku-4-5-20251001',
        'confidence_threshold' => 0.75,
        'max_consecutive_ai_replies' => 3,
        'brand_voice' => '',
        'review_mode' => false,
        'vision_enabled' => false,
        'daily_run_limit' => 300,
        'max_run_tokens' => 20000,
        'daily_budget_usd' => 0,
        'input_cost_per_million' => 0,
        'output_cost_per_million' => 0,
        'sales_follow_ups_enabled' => false,
        'follow_up_delay_hours' => 4,
        'follow_up_template' => '',
        'follow_up_template_language' => 'bn',
    ];

    public function all(Company $company, string $tool): array
    {
        $stored = (array) data_get($company->settings, "ai_tools.{$tool}", []);

        if ($stored === []) {
            $stored = $this->legacyGlobalSettings($company) ?? [];
        }

        $settings = array_merge(self::DEFAULTS, $stored);
        $settings['sales_guidelines'] = $stored['sales_guidelines'] ?? ($tool === self::TOOL_MESSAGING ? $this->defaultSalesGuidelines() : '');
        $settings['api_key'] = $this->decrypt($stored['api_key'] ?? null);

        return $settings;
    }

    public function save(Company $company, string $tool, array $data): void
    {
        $settings = (array) $company->settings;
        $existing = (array) data_get($settings, "ai_tools.{$tool}", []);

        $settings['ai_tools'][$tool] = [
            'enabled' => (bool) ($data['enabled'] ?? false),
            'provider' => trim((string) ($data['provider'] ?? '')) ?: self::DEFAULTS['provider'],
            'api_format' => in_array($data['api_format'] ?? '', ['anthropic', 'openai'], true)
                ? $data['api_format']
                : self::DEFAULTS['api_format'],
            'base_url' => trim((string) ($data['base_url'] ?? '')),
            'model' => trim((string) ($data['model'] ?? self::DEFAULTS['model'])) ?: self::DEFAULTS['model'],
            'confidence_threshold' => min(max((float) ($data['confidence_threshold'] ?? 0.75), 0), 1),
            'max_consecutive_ai_replies' => max((int) ($data['max_consecutive_ai_replies'] ?? 3), 1),
            'brand_voice' => trim((string) ($data['brand_voice'] ?? '')),
            'sales_guidelines' => mb_substr(trim((string) ($data['sales_guidelines'] ?? $existing['sales_guidelines'] ?? ($tool === self::TOOL_MESSAGING ? $this->defaultSalesGuidelines() : ''))), 0, 16000),
            'review_mode' => (bool) ($data['review_mode'] ?? $existing['review_mode'] ?? false),
            'vision_enabled' => (bool) ($data['vision_enabled'] ?? $existing['vision_enabled'] ?? false),
            'daily_run_limit' => max(1, min(10000, (int) ($data['daily_run_limit'] ?? $existing['daily_run_limit'] ?? 300))),
            'max_run_tokens' => max(3000, min(50000, (int) ($data['max_run_tokens'] ?? $existing['max_run_tokens'] ?? 20000))),
            'daily_budget_usd' => max(0, (float) ($data['daily_budget_usd'] ?? $existing['daily_budget_usd'] ?? 0)),
            'input_cost_per_million' => max(0, (float) ($data['input_cost_per_million'] ?? $existing['input_cost_per_million'] ?? 0)),
            'output_cost_per_million' => max(0, (float) ($data['output_cost_per_million'] ?? $existing['output_cost_per_million'] ?? 0)),
            'sales_follow_ups_enabled' => (bool) ($data['sales_follow_ups_enabled'] ?? $existing['sales_follow_ups_enabled'] ?? false),
            'follow_up_delay_hours' => max(1, min(168, (int) ($data['follow_up_delay_hours'] ?? $existing['follow_up_delay_hours'] ?? 4))),
            'follow_up_template' => trim((string) ($data['follow_up_template'] ?? $existing['follow_up_template'] ?? '')),
            'follow_up_template_language' => trim((string) ($data['follow_up_template_language'] ?? $existing['follow_up_template_language'] ?? 'bn')),
            'api_key' => filled($data['api_key'] ?? null)
                ? Crypt::encryptString(trim((string) $data['api_key']))
                : ($existing['api_key'] ?? null), // keep existing when left blank
        ];

        $company->forceFill(['settings' => $settings])->save();
    }

    public function enabled(Company $company, string $tool): bool
    {
        $settings = $this->all($company, $tool);

        return $settings['enabled'] && filled($settings['api_key']);
    }

    public function defaultSalesGuidelines(): string
    {
        return trim(file_get_contents(resource_path('ai/sales-agent-guidelines.bn.md')));
    }

    /**
     * The old single shared `settings->ai` blob, still passed through the
     * pre-existing provider-enum migration below so a company that never
     * touched AI settings since before *that* upgrade keeps working too.
     * Returns null when nothing was ever configured at all.
     */
    protected function legacyGlobalSettings(Company $company): ?array
    {
        $stored = (array) data_get($company->settings, 'ai', []);

        if ($stored === []) {
            return null;
        }

        return $this->migrateLegacySettings($stored);
    }

    /**
     * Back-compat for settings saved before the provider list became
     * flexible, when `provider` was a closed 'anthropic'|'openai'|'deepseek'
     * enum that also doubled as the wire format. Maps those forward to the
     * new provider label + api_format + base_url shape so companies
     * configured before this upgrade keep working unchanged.
     */
    protected function migrateLegacySettings(array $stored): array
    {
        if (! isset($stored['provider']) || isset($stored['api_format'])) {
            return $stored;
        }

        return array_merge($stored, match ($stored['provider']) {
            'openai' => ['provider' => 'OpenAI', 'api_format' => 'openai', 'base_url' => ''],
            'deepseek' => ['provider' => 'DeepSeek', 'api_format' => 'openai', 'base_url' => 'https://api.deepseek.com/chat/completions'],
            default => ['provider' => 'Anthropic (Claude)', 'api_format' => 'anthropic', 'base_url' => ''],
        });
    }

    protected function decrypt(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }
}
