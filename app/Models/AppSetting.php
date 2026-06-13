<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AppSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'is_encrypted',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        if ($setting->is_encrypted && filled($setting->value)) {
            return Crypt::decryptString($setting->value);
        }

        return $setting->value ?? $default;
    }

    public static function setValue(string $key, mixed $value, bool $encrypted = false): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $encrypted && filled($value) ? Crypt::encryptString((string) $value) : $value,
                'is_encrypted' => $encrypted,
            ],
        );
    }

    public static function boolValue(string $key, bool $default = false): bool
    {
        return filter_var(static::getValue($key, $default ? '1' : '0'), FILTER_VALIDATE_BOOLEAN);
    }
}
