<?php

namespace App\Services\PromptEnhancement;

use App\Models\Company;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * The shared Prompt Enhancer's own provider/model/key, stored per company at
 * `companies.settings->ai_tools->prompt_enhancer`. The API key is encrypted
 * at rest — admin-configurable, never hardcoded (CLAUDE.md rule).
 *
 * A flat config, deliberately NOT routed through App\Services\Crm\AiSettingsService:
 * that service is the CRM text tools' per-tool store (messaging / ad assistant /
 * landing page) and the Prompt Enhancer is configured on its own dedicated page
 * under AI Tools instead — same self-contained pattern as
 * App\Services\ImageGeneration\ImageProviderSettingsService. There is nothing to
 * inherit from the old shared chat config, so there is no legacy fallback.
 */
class PromptEnhancerConfigService
{
    public const SETTINGS_PATH = 'ai_tools.prompt_enhancer';

    /** @var array<string, mixed> */
    public const DEFAULTS = [
        'enabled' => false,
        'api_format' => 'anthropic',
        'provider' => 'Anthropic (Claude)',
        'base_url' => '',
        'model' => 'claude-haiku-4-5-20251001',
        'api_key' => null,
    ];

    /**
     * The full config, API key decrypted. Never hand this shape to a browser.
     *
     * @return array<string, mixed>
     */
    public function all(Company $company): array
    {
        $stored = (array) data_get($company->settings, self::SETTINGS_PATH, []);

        $config = [...self::DEFAULTS, ...$stored];
        $config['enabled'] = (bool) $config['enabled'];
        $config['api_format'] = in_array($config['api_format'], ['anthropic', 'openai'], true) ? $config['api_format'] : 'anthropic';
        $config['api_key'] = $this->decrypt($config['api_key'] ?? null);

        return $config;
    }

    public function isConfigured(Company $company): bool
    {
        $config = $this->all($company);

        return $config['enabled'] && filled($config['api_key']);
    }

    /**
     * Persist the config. A blank API key keeps the stored (encrypted) one.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(Company $company, array $data): void
    {
        $stored = (array) data_get($company->settings, self::SETTINGS_PATH, []);
        $submittedKey = trim((string) ($data['api_key'] ?? ''));

        $next = [
            'enabled' => (bool) ($data['enabled'] ?? false),
            'api_format' => in_array($data['api_format'] ?? null, ['anthropic', 'openai'], true) ? $data['api_format'] : 'anthropic',
            'provider' => trim((string) ($data['provider'] ?? '')),
            'base_url' => trim((string) ($data['base_url'] ?? '')),
            'model' => trim((string) ($data['model'] ?? '')),
            'api_key' => $submittedKey !== ''
                ? Crypt::encryptString($submittedKey)
                : ($stored['api_key'] ?? null),
        ];

        $settings = (array) $company->settings;
        data_set($settings, self::SETTINGS_PATH, $next);
        $company->forceFill(['settings' => $settings])->save();
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
