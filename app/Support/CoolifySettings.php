<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Admin-configurable Coolify credentials (instance URL, API token, and the
 * app's own Application UUID on that instance), stored via the existing
 * global AppSetting encrypted key/value store instead of `.env` -- per
 * CLAUDE.md, external credentials must be admin-configurable encrypted
 * settings fields, not deployment config. There is exactly one Coolify
 * instance hosting this app, so this is intentionally global, not
 * per-company -- same scope as App\Support\FirebaseSettings.
 *
 * Powers App\Services\CoolifyDeploymentService (App\Filament\Pages\
 * AppVersions' "rollback to a previous deployment" feature).
 */
class CoolifySettings
{
    /**
     * Field key => [AppSetting key, whether it is stored encrypted].
     *
     * @var array<string, array{key: string, encrypted: bool}>
     */
    public const FIELDS = [
        'enabled' => ['key' => 'coolify.enabled', 'encrypted' => false],
        'base_url' => ['key' => 'coolify.base_url', 'encrypted' => true],
        'api_token' => ['key' => 'coolify.api_token', 'encrypted' => true],
        'application_uuid' => ['key' => 'coolify.application_uuid', 'encrypted' => true],
    ];

    public static function schemaIsReady(): bool
    {
        return Schema::hasTable('app_settings');
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        if (! self::schemaIsReady()) {
            return array_fill_keys(array_keys(self::FIELDS), null);
        }

        return collect(self::FIELDS)
            ->mapWithKeys(fn (array $meta, string $field): array => [
                $field => $field === 'enabled'
                    ? AppSetting::boolValue($meta['key'], false)
                    : AppSetting::getValue($meta['key']),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function save(array $data): void
    {
        foreach (self::FIELDS as $field => $meta) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            if ($field === 'enabled') {
                AppSetting::setValue($meta['key'], $data[$field] ? '1' : '0');

                continue;
            }

            $value = is_string($data[$field]) ? trim($data[$field]) : $data[$field];

            if ($field === 'base_url' && is_string($value) && $value !== '') {
                $value = Str::rtrim($value, '/');
            }

            AppSetting::setValue($meta['key'], $value, encrypted: true);
        }
    }

    public static function enabled(): bool
    {
        return self::schemaIsReady() && AppSetting::boolValue(self::FIELDS['enabled']['key'], false);
    }

    public static function baseUrl(): ?string
    {
        return self::field('base_url');
    }

    public static function apiToken(): ?string
    {
        return self::field('api_token');
    }

    public static function applicationUuid(): ?string
    {
        return self::field('application_uuid');
    }

    public static function isConfigured(): bool
    {
        return self::enabled()
            && filled(self::baseUrl())
            && filled(self::apiToken())
            && filled(self::applicationUuid());
    }

    protected static function field(string $field): ?string
    {
        if (! self::schemaIsReady()) {
            return null;
        }

        $meta = self::FIELDS[$field] ?? null;

        if (! $meta) {
            return null;
        }

        $value = AppSetting::getValue($meta['key']);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
