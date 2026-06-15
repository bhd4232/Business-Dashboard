<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LicenseActivationService
{
    public const LICENSE_KEY = 'license.key';

    public const LICENSED_TO = 'license.licensed_to';

    public const SUPPORT_EMAIL = 'license.support_email';

    public const ACTIVATED_AT = 'license.activated_at';

    public function details(): array
    {
        $key = (string) $this->value(self::LICENSE_KEY, '');

        return [
            'key' => $key,
            'masked_key' => $this->mask($key),
            'licensed_to' => $this->value(self::LICENSED_TO, ''),
            'support_email' => $this->value(self::SUPPORT_EMAIL, ''),
            'activated_at' => $this->value(self::ACTIVATED_AT),
            'is_active' => $this->isActive(),
        ];
    }

    public function activate(array $data): bool
    {
        $key = strtoupper(trim((string) ($data['key'] ?? '')));

        if (! $this->isValidKey($key)) {
            return false;
        }

        AppSetting::setValue(self::LICENSE_KEY, $key);
        AppSetting::setValue(self::LICENSED_TO, trim((string) ($data['licensed_to'] ?? '')));
        AppSetting::setValue(self::SUPPORT_EMAIL, trim((string) ($data['support_email'] ?? '')));
        AppSetting::setValue(self::ACTIVATED_AT, now()->toDateTimeString());

        return true;
    }

    public function isActive(): bool
    {
        return $this->isValidKey((string) $this->value(self::LICENSE_KEY, ''));
    }

    public function isValidKey(string $key): bool
    {
        return preg_match('/^ZZERP-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', strtoupper($key)) === 1;
    }

    protected function mask(string $key): string
    {
        if ($key === '') {
            return 'Not activated';
        }

        return Str::of($key)->mask('*', 10, max(strlen($key) - 14, 0))->toString();
    }

    protected function value(string $key, mixed $default = null): mixed
    {
        if (! Schema::hasTable('app_settings')) {
            return $default;
        }

        return AppSetting::getValue($key, $default);
    }
}
