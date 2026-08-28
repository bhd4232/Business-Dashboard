<?php

namespace App\Services\Crm;

use App\Models\Company;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Per-company AI auto-reply settings, stored in the existing
 * `companies.settings` json column ('ai' key). The API key is encrypted at
 * rest — admin-configurable, never hardcoded (CLAUDE.md rule).
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
 */
class AiSettingsService
{
    public const DEFAULTS = [
        'enabled' => false,
        'provider' => 'Anthropic (Claude)', // free-text label, shown in the UI/audit trail only
        'api_format' => 'anthropic', // anthropic | openai — the actual request/response wire format
        'base_url' => '', // optional endpoint override; blank = api_format's own default endpoint
        'model' => 'claude-haiku-4-5-20251001',
        'confidence_threshold' => 0.75,
        'max_consecutive_ai_replies' => 3,
        'brand_voice' => '',
    ];

    public function all(Company $company): array
    {
        $stored = $this->migrateLegacySettings((array) data_get($company->settings, 'ai', []));
        $settings = array_merge(self::DEFAULTS, $stored);
        $settings['api_key'] = $this->decrypt($stored['api_key'] ?? null);

        return $settings;
    }

    public function save(Company $company, array $data): void
    {
        $settings = (array) $company->settings;

        $settings['ai'] = [
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
            'api_key' => filled($data['api_key'] ?? null)
                ? Crypt::encryptString(trim((string) $data['api_key']))
                : (data_get($company->settings, 'ai.api_key') ?? null), // keep existing when left blank
        ];

        $company->forceFill(['settings' => $settings])->save();
    }

    public function enabled(Company $company): bool
    {
        $settings = $this->all($company);

        return $settings['enabled'] && filled($settings['api_key']);
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
