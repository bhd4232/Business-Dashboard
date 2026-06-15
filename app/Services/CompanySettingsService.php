<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CompanySettingsService
{
    public const NAME = 'company.name';

    public const LOGO = 'company.logo';

    public const DARK_LOGO = 'company.dark_logo';

    public const ADDRESS = 'company.address';

    public const PHONE = 'company.phone';

    public const EMAIL = 'company.email';

    public const CURRENCY = 'company.currency';

    public const TIMEZONE = 'company.timezone';

    public const DATE_FORMAT = 'company.date_format';

    public function profile(): array
    {
        return [
            'name' => $this->value(self::NAME, config('app.name', 'Business Dashboard')),
            'logo' => $this->value(self::LOGO),
            'dark_logo' => $this->value(self::DARK_LOGO),
            'logo_url' => $this->logoUrl(),
            'dark_logo_url' => $this->darkLogoUrl(),
            'logo_path' => $this->logoPath(),
            'dark_logo_path' => $this->darkLogoPath(),
            'address' => $this->value(self::ADDRESS),
            'phone' => $this->value(self::PHONE),
            'email' => $this->value(self::EMAIL),
            'currency' => $this->value(self::CURRENCY, 'BDT'),
            'timezone' => $this->value(self::TIMEZONE, config('app.timezone', 'UTC')),
            'date_format' => $this->value(self::DATE_FORMAT, 'd M Y'),
        ];
    }

    public function save(array $data): void
    {
        AppSetting::setValue(self::NAME, trim((string) ($data['name'] ?? '')));
        AppSetting::setValue(self::LOGO, trim((string) ($data['logo'] ?? '')));
        AppSetting::setValue(self::DARK_LOGO, trim((string) ($data['dark_logo'] ?? '')));
        AppSetting::setValue(self::ADDRESS, trim((string) ($data['address'] ?? '')));
        AppSetting::setValue(self::PHONE, trim((string) ($data['phone'] ?? '')));
        AppSetting::setValue(self::EMAIL, trim((string) ($data['email'] ?? '')));
        AppSetting::setValue(self::CURRENCY, trim((string) ($data['currency'] ?? 'BDT')));
        AppSetting::setValue(self::TIMEZONE, trim((string) ($data['timezone'] ?? config('app.timezone', 'UTC'))));
        AppSetting::setValue(self::DATE_FORMAT, trim((string) ($data['date_format'] ?? 'd M Y')));
    }

    public function logoUrl(): ?string
    {
        return $this->publicUrl($this->value(self::LOGO));
    }

    public function darkLogoUrl(bool $fallbackToLight = true): ?string
    {
        return $this->publicUrl($this->value(self::DARK_LOGO)) ?: ($fallbackToLight ? $this->logoUrl() : null);
    }

    public function logoPath(): ?string
    {
        return $this->publicPath($this->value(self::LOGO));
    }

    public function darkLogoPath(bool $fallbackToLight = true): ?string
    {
        return $this->publicPath($this->value(self::DARK_LOGO)) ?: ($fallbackToLight ? $this->logoPath() : null);
    }

    protected function publicUrl(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    protected function publicPath(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->path($path);
    }

    public function formatMoney(float|int|string|null $amount): string
    {
        return $this->profile()['currency'].' '.number_format((float) $amount, 2);
    }

    public function formatDate($date): string
    {
        if (! $date) {
            return '-';
        }

        return $date->timezone($this->profile()['timezone'])->format($this->profile()['date_format']);
    }

    protected function value(string $key, mixed $default = null): mixed
    {
        if (! Schema::hasTable('app_settings')) {
            return $default;
        }

        return AppSetting::getValue($key, $default);
    }
}
