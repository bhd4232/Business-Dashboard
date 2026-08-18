<?php

namespace App\Services\Meta;

use App\Models\Company;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Per-company Meta Embedded Signup app credentials, stored in the existing
 * `companies.settings` json column ('embedded_signup' key), mirroring
 * AiSettingsService's shape exactly. app_secret is encrypted at rest -
 * admin-configurable, never hardcoded (CLAUDE.md rule). These are the same
 * Meta App ID/Secret used to verify inbound webhook signatures, so they are
 * copied onto each ConversationChannel row created through this flow
 * (see EmbeddedSignupService::complete()).
 */
class EmbeddedSignupSettingsService
{
    public const DEFAULTS = [
        'app_id' => '',
        'config_id' => '',
        'verify_token' => '',
    ];

    public function all(Company $company): array
    {
        $stored = (array) data_get($company->settings, 'embedded_signup', []);
        $settings = array_merge(self::DEFAULTS, $stored);
        $settings['app_secret'] = $this->decrypt($stored['app_secret'] ?? null);

        return $settings;
    }

    public function save(Company $company, array $data): void
    {
        $settings = (array) $company->settings;

        $settings['embedded_signup'] = [
            'app_id' => trim((string) ($data['app_id'] ?? '')),
            'config_id' => trim((string) ($data['config_id'] ?? '')),
            'verify_token' => trim((string) ($data['verify_token'] ?? '')),
            'app_secret' => filled($data['app_secret'] ?? null)
                ? Crypt::encryptString(trim((string) $data['app_secret']))
                : (data_get($company->settings, 'embedded_signup.app_secret') ?? null), // keep existing when left blank
        ];

        $company->forceFill(['settings' => $settings])->save();
    }

    public function isConfigured(Company $company): bool
    {
        $settings = $this->all($company);

        return filled($settings['app_id']) && filled($settings['config_id']) && filled($settings['app_secret']);
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
