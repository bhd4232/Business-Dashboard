<?php

namespace App\Services\ExpenseScan;

use App\Models\Company;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * AI Expense Scan's own provider/model/key, stored per company at
 * `companies.settings->ai_tools->expense_scan`. Kept separate from the CRM
 * and Prompt Enhancer keys (owner's call) so its usage and cost can be
 * tracked on its own. The API key is encrypted at rest — admin-configurable,
 * never hardcoded (CLAUDE.md rule).
 *
 * The model must be vision-capable (it reads photos of handwritten notes).
 */
class ExpenseScanConfigService
{
    public const SETTINGS_PATH = 'ai_tools.expense_scan';

    /** @var array<string, mixed> */
    public const DEFAULTS = [
        'enabled' => false,
        'api_format' => 'anthropic',
        'provider' => 'Anthropic (Claude)',
        'base_url' => '',
        'model' => 'claude-opus-5',
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

        return $config['enabled'] && filled($config['api_key']) && filled($config['model']);
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
