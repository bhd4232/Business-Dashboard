<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Schema;

class ProductSetupService
{
    public const INSTALLED = 'product.installed';

    public const ONBOARDING_COMPLETED = 'product.onboarding_completed';

    public const DEMO_MODE = 'product.demo_mode';

    public const DEMO_NOTICE = 'product.demo_notice';

    public function installed(): bool
    {
        return $this->bool(self::INSTALLED) || $this->onboardingCompleted();
    }

    public function onboardingCompleted(): bool
    {
        return $this->bool(self::ONBOARDING_COMPLETED);
    }

    public function demoMode(): bool
    {
        return $this->bool(self::DEMO_MODE);
    }

    public function demoNotice(): string
    {
        return (string) $this->value(self::DEMO_NOTICE, 'Demo mode is enabled. Use sample data only.');
    }

    public function save(array $data): void
    {
        AppSetting::setValue(self::INSTALLED, ! empty($data['installed']) ? '1' : '0');
        AppSetting::setValue(self::ONBOARDING_COMPLETED, ! empty($data['onboarding_completed']) ? '1' : '0');
        AppSetting::setValue(self::DEMO_MODE, ! empty($data['demo_mode']) ? '1' : '0');
        AppSetting::setValue(self::DEMO_NOTICE, trim((string) ($data['demo_notice'] ?? '')));
    }

    protected function bool(string $key): bool
    {
        if (! Schema::hasTable('app_settings')) {
            return false;
        }

        return AppSetting::boolValue($key);
    }

    protected function value(string $key, mixed $default = null): mixed
    {
        if (! Schema::hasTable('app_settings')) {
            return $default;
        }

        return AppSetting::getValue($key, $default);
    }
}
