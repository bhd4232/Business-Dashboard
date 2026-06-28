<?php

namespace App\Services;

use App\Models\AppSetting;

class CustomerRiskSettingsService
{
    public const KEY = 'customer_risk.rules';

    public const DEFAULTS = [
        'high_cod_amount' => 5000,
        'high_return_ratio_threshold' => 50,
        'low_success_total_orders' => 2,
        'low_success_ratio_threshold' => 50,
        'high_return_ratio_deduction' => 30,
        'low_success_ratio_deduction' => 20,
        'phone_multiple_names_deduction' => 15,
        'high_cod_first_order_deduction' => 15,
        'incomplete_address_deduction' => 10,
        'recent_duplicate_order_deduction' => 10,
        'repeated_cancellation_deduction' => 20,
        'blacklist_match_deduction' => 50,
    ];

    public function all(): array
    {
        $saved = json_decode((string) AppSetting::getValue(self::KEY, '{}'), true);

        return array_replace(self::DEFAULTS, is_array($saved) ? $saved : []);
    }

    public function save(array $settings): void
    {
        $clean = [];

        foreach (self::DEFAULTS as $key => $default) {
            $clean[$key] = max(0, (int) ($settings[$key] ?? $default));
        }

        AppSetting::setValue(self::KEY, json_encode($clean));
    }

    public function int(string $key): int
    {
        return (int) $this->all()[$key];
    }
}
