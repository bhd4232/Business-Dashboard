<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Admin-configurable Cloudflare R2 (S3-compatible) storage credentials.
 *
 * Follows the same DB-backed, encrypted-value pattern as
 * BackupSettingsService/AppSetting — nothing here is read from .env, so an
 * owner can paste their R2 bucket credentials from the ERP settings page
 * without a redeploy. When enabled and fully configured, AppServiceProvider
 * points the app's "public" filesystem disk at R2 for the rest of the
 * request; every existing `Storage::disk('public')` call (product images,
 * category/company logos, storefront slides/banners) then transparently
 * writes to and serves from R2 instead of local disk.
 */
class StorageSettingsService
{
    public const ENABLED = 'storage.r2.enabled';

    public const ACCESS_KEY_ID = 'storage.r2.access_key_id';

    public const SECRET_ACCESS_KEY = 'storage.r2.secret_access_key';

    public const BUCKET = 'storage.r2.bucket';

    public const ENDPOINT = 'storage.r2.endpoint';

    public const PUBLIC_URL = 'storage.r2.public_url';

    public function enabled(): bool
    {
        if (! $this->settingsTableExists()) {
            return false;
        }

        return AppSetting::boolValue(self::ENABLED, false);
    }

    public function accessKeyId(): ?string
    {
        return $this->settingsTableExists() ? AppSetting::getValue(self::ACCESS_KEY_ID) : null;
    }

    public function hasSecretAccessKey(): bool
    {
        return $this->settingsTableExists() && filled(AppSetting::getValue(self::SECRET_ACCESS_KEY));
    }

    public function bucket(): ?string
    {
        return $this->settingsTableExists() ? AppSetting::getValue(self::BUCKET) : null;
    }

    public function endpoint(): ?string
    {
        return $this->settingsTableExists() ? AppSetting::getValue(self::ENDPOINT) : null;
    }

    public function publicUrl(): ?string
    {
        return $this->settingsTableExists() ? AppSetting::getValue(self::PUBLIC_URL) : null;
    }

    protected function secretAccessKey(): ?string
    {
        return $this->settingsTableExists() ? AppSetting::getValue(self::SECRET_ACCESS_KEY) : null;
    }

    /**
     * A value can only be saved (or left untouched) — an admin can never
     * accidentally wipe the secret key by leaving the field blank in the UI,
     * since blank means "keep the existing one" (same convention as the
     * Google Drive service-account-JSON field on the Backups page).
     */
    public function save(array $data): void
    {
        AppSetting::setValue(self::ENABLED, ! empty($data['enabled']) ? '1' : '0');
        AppSetting::setValue(self::ACCESS_KEY_ID, trim((string) ($data['access_key_id'] ?? '')));
        AppSetting::setValue(self::BUCKET, trim((string) ($data['bucket'] ?? '')));
        AppSetting::setValue(self::ENDPOINT, rtrim(trim((string) ($data['endpoint'] ?? '')), '/'));
        AppSetting::setValue(self::PUBLIC_URL, rtrim(trim((string) ($data['public_url'] ?? '')), '/'));

        if (filled($data['secret_access_key'] ?? null)) {
            AppSetting::setValue(self::SECRET_ACCESS_KEY, trim((string) $data['secret_access_key']), encrypted: true);
        }
    }

    public function isConfigured(): bool
    {
        return filled($this->accessKeyId())
            && $this->hasSecretAccessKey()
            && filled($this->bucket())
            && filled($this->endpoint())
            && filled($this->publicUrl());
    }

    /**
     * @return array<string, mixed>
     */
    public function diskConfig(): array
    {
        return [
            'driver' => 's3',
            'key' => $this->accessKeyId(),
            'secret' => $this->secretAccessKey(),
            'region' => 'auto',
            'bucket' => $this->bucket(),
            'endpoint' => $this->endpoint(),
            'url' => $this->publicUrl(),
            'use_path_style_endpoint' => true,
            'visibility' => 'public',
            'throw' => true,
            'report' => false,
        ];
    }

    /**
     * Builds an ad-hoc disk from the saved credentials and round-trips a
     * tiny marker file, so "Test connection" in the UI reflects reality
     * rather than just "the fields are non-empty".
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Fill in access key, secret key, bucket, endpoint, and public URL first.'];
        }

        try {
            $disk = $this->buildDisk();
            $path = 'zamzam-r2-connection-test-'.Str::random(8).'.txt';

            $disk->put($path, 'ok');
            $readBack = $disk->get($path);
            $disk->delete($path);

            if ($readBack !== 'ok') {
                return ['ok' => false, 'message' => 'Connected, but the written test file could not be read back correctly.'];
            }

            return ['ok' => true, 'message' => 'Connected — a test file was written to and deleted from the bucket successfully.'];
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => 'Connection failed: '.$exception->getMessage()];
        }
    }

    public function buildDisk(): Filesystem
    {
        return Storage::build($this->diskConfig());
    }

    protected function settingsTableExists(): bool
    {
        return Schema::hasTable('app_settings');
    }
}
