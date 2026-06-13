<?php

namespace App\Filament\Forms\Components;

class PhoneInput
{
    public static function make(string $field = 'phone', string $label = 'Phone', bool $required = false): PhoneNumberInput
    {
        return PhoneNumberInput::make($field)
            ->label($label)
            ->required($required)
            ->rules(['max:255']);
    }

    public static function formatPhoneNumber(?string $phone, string $countryCode = '+880'): ?string
    {
        if (blank($phone)) {
            return null;
        }

        $phone = trim($phone);
        $countryCode = self::normalizeCountryCode($countryCode);

        if (str_starts_with($phone, '+')) {
            return preg_replace('/\s+/', '', $phone);
        }

        $number = preg_replace('/[^\d]/', '', $phone);

        if ($number === '') {
            return null;
        }

        return $countryCode . ltrim($number, '0');
    }

    public static function splitPhoneNumber(?string $phone): array
    {
        $phone = trim((string) $phone);

        foreach (array_keys(self::countryCodeOptions()) as $countryCode) {
            if (str_starts_with($phone, $countryCode)) {
                return [$countryCode, substr($phone, strlen($countryCode))];
            }
        }

        return ['+880', $phone];
    }

    public static function countryCodeOptions(): array
    {
        $options = [];

        foreach (PhoneNumberInput::countryOptionsData() as $country) {
            $options[$country['code']] ??= "{$country['country']} ({$country['code']})";
        }

        return $options;
    }

    protected static function normalizeCountryCode(string $countryCode): string
    {
        $countryCode = trim($countryCode);

        return str_starts_with($countryCode, '+') ? $countryCode : "+{$countryCode}";
    }
}
