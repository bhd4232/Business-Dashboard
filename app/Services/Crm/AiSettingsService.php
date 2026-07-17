<?php

namespace App\Services\Crm;

use App\Models\Company;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Per-company AI auto-reply settings, stored in the existing
 * `companies.settings` json column ('ai' key). The API key is encrypted at
 * rest — admin-configurable, never hardcoded (CLAUDE.md rule).
 */
class AiSettingsService
{
    public const DEFAULTS = [
        'enabled' => false,
        'provider' => 'anthropic', // anthropic | openai
        'model' => 'claude-haiku-4-5-20251001',
        'confidence_threshold' => 0.75,
        'max_consecutive_ai_replies' => 3,
        'brand_voice' => '',
    ];

    public function all(Company $company): array
    {
        $stored = (array) data_get($company->settings, 'ai', []);
        $settings = array_merge(self::DEFAULTS, $stored);
        $settings['api_key'] = $this->decrypt($stored['api_key'] ?? null);

        return $settings;
    }

    public function save(Company $company, array $data): void
    {
        $settings = (array) $company->settings;

        $settings['ai'] = [
            'enabled' => (bool) ($data['enabled'] ?? false),
            'provider' => in_array($data['provider'] ?? '', ['anthropic', 'openai'], true)
                ? $data['provider']
                : self::DEFAULTS['provider'],
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
