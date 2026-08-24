<?php

namespace App\Support;

class MoneyFormatter
{
    public static function symbol(string $currency = 'BDT'): string
    {
        return match (strtoupper(trim($currency))) {
            'BDT' => '৳',
            'USD', 'CAD', 'AUD', 'NZD', 'SGD', 'HKD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'AED' => 'د.إ',
            'INR' => '₹',
            'JPY', 'CNY' => '¥',
            'KRW' => '₩',
            'THB' => '฿',
            'MYR' => 'RM',
            'PKR' => '₨',
            default => '¤',
        };
    }

    public static function label(string $currency = 'BDT'): string
    {
        $currency = strtoupper(trim($currency)) ?: 'BDT';

        return "{$currency} (".self::symbol($currency).')';
    }

    public static function number(float|int|string|null $amount, int $maxDecimals = 2): string
    {
        if (! is_numeric($amount)) {
            return '0';
        }

        $formatted = number_format((float) $amount, $maxDecimals, '.', ',');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    public static function currency(float|int|string|null $amount, string $currency = 'BDT', int $maxDecimals = 2): string
    {
        return self::symbol($currency).' '.self::number($amount, $maxDecimals);
    }
}
