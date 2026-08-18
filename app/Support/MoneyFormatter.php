<?php

namespace App\Support;

class MoneyFormatter
{
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
        return trim($currency).' '.self::number($amount, $maxDecimals);
    }
}
