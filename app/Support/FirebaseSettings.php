<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Schema;
use JsonException;

/**
 * Admin-configurable Firebase credentials (Web SDK config, VAPID key, and
 * the server-side service-account JSON), stored via the existing global
 * AppSetting encrypted key/value store instead of `.env` -- per CLAUDE.md,
 * external credentials must be admin-configurable encrypted settings
 * fields, not deployment config. There is exactly one Firebase project for
 * the whole app (it registers one Android app and one Web app), so this is
 * intentionally global, not per-company -- same scope as the existing
 * app-update push feature.
 */
class FirebaseSettings
{
    /**
     * Field key => [AppSetting key, whether it is stored encrypted].
     *
     * @var array<string, array{key: string, encrypted: bool}>
     */
    public const FIELDS = [
        'enabled' => ['key' => 'firebase.enabled', 'encrypted' => false],
        'api_key' => ['key' => 'firebase.web.api_key', 'encrypted' => true],
        'auth_domain' => ['key' => 'firebase.web.auth_domain', 'encrypted' => true],
        'project_id' => ['key' => 'firebase.web.project_id', 'encrypted' => true],
        'storage_bucket' => ['key' => 'firebase.web.storage_bucket', 'encrypted' => true],
        'messaging_sender_id' => ['key' => 'firebase.web.messaging_sender_id', 'encrypted' => true],
        'app_id' => ['key' => 'firebase.web.app_id', 'encrypted' => true],
        'vapid_key' => ['key' => 'firebase.web.vapid_key', 'encrypted' => true],
        'service_account_json' => ['key' => 'firebase.service_account_json', 'encrypted' => true],
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

            AppSetting::setValue($meta['key'], $value, encrypted: true);
        }
    }

    public static function enabled(): bool
    {
        return self::schemaIsReady() && AppSetting::boolValue(self::FIELDS['enabled']['key'], false);
    }

    /**
     * @return array{apiKey: ?string, authDomain: ?string, projectId: ?string, storageBucket: ?string, messagingSenderId: ?string, appId: ?string}
     */
    public static function webConfig(): array
    {
        return [
            'apiKey' => self::field('api_key'),
            'authDomain' => self::field('auth_domain'),
            'projectId' => self::field('project_id'),
            'storageBucket' => self::field('storage_bucket'),
            'messagingSenderId' => self::field('messaging_sender_id'),
            'appId' => self::field('app_id'),
        ];
    }

    public static function vapidKey(): ?string
    {
        return self::field('vapid_key');
    }

    public static function isWebConfigured(): bool
    {
        if (! self::enabled()) {
            return false;
        }

        $config = self::webConfig();

        return filled($config['apiKey'])
            && filled($config['projectId'])
            && filled($config['appId'])
            && filled(self::vapidKey());
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function serviceAccountJson(): ?array
    {
        $raw = self::field('service_account_json');

        if (blank($raw)) {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    public static function isServiceAccountConfigured(): bool
    {
        if (! self::enabled()) {
            return false;
        }

        $credentials = self::serviceAccountJson();

        return filled($credentials['client_email'] ?? null) && filled($credentials['private_key'] ?? null);
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
