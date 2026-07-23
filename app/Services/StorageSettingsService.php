<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Global Cloudflare R2 connection settings.
 *
 * Credentials are shared infrastructure, while CompanyStorageService owns
 * tenant path isolation. Public and private objects may use separate buckets.
 */
class StorageSettingsService
{
    public const ENABLED = 'storage.r2.enabled';

    public const ACCESS_KEY_ID = 'storage.r2.access_key_id';

    public const SECRET_ACCESS_KEY = 'storage.r2.secret_access_key';

    /** @deprecated Use PUBLIC_BUCKET. */
    public const BUCKET = 'storage.r2.bucket';

    public const PUBLIC_BUCKET = 'storage.r2.public_bucket';

    public const PRIVATE_BUCKET = 'storage.r2.private_bucket';

    public const PRIVATE_ACCESS_CONFIRMED = 'storage.r2.private_public_access_disabled';

    public const ENDPOINT = 'storage.r2.endpoint';

    public const PUBLIC_URL = 'storage.r2.public_url';

    public const PUBLIC_TOPOLOGY_LOCKED = 'storage.r2.public_topology_locked';

    public const PRIVATE_TOPOLOGY_LOCKED = 'storage.r2.private_topology_locked';

    /** @var array<string, mixed> */
    protected array $cachedValues = [];

    protected ?bool $settingsTableAvailable = null;

    /** @var array<string, array<string, mixed>> */
    protected array $baseDiskConfigs;

    public function __construct()
    {
        $this->baseDiskConfigs = [
            'r2' => (array) config('filesystems.disks.r2', []),
            'r2_public' => (array) config('filesystems.disks.r2_public', []),
            'r2_private' => (array) config('filesystems.disks.r2_private', []),
        ];
    }

    public function enabled(): bool
    {
        return filter_var($this->value(self::ENABLED, '0'), FILTER_VALIDATE_BOOLEAN);
    }

    public function accessKeyId(): ?string
    {
        return $this->nullableString($this->value(self::ACCESS_KEY_ID));
    }

    public function hasSecretAccessKey(): bool
    {
        return filled($this->secretAccessKey());
    }

    /** @deprecated Use publicBucket(). */
    public function bucket(): ?string
    {
        return $this->publicBucket();
    }

    public function publicBucket(): ?string
    {
        return $this->nullableString(
            $this->value(self::PUBLIC_BUCKET) ?: $this->value(self::BUCKET),
        );
    }

    public function privateBucket(): ?string
    {
        return $this->nullableString($this->value(self::PRIVATE_BUCKET));
    }

    public function privateAccessConfirmed(): bool
    {
        return filter_var($this->value(self::PRIVATE_ACCESS_CONFIRMED, '0'), FILTER_VALIDATE_BOOLEAN);
    }

    public function endpoint(): ?string
    {
        return $this->nullableString($this->value(self::ENDPOINT));
    }

    public function publicUrl(): ?string
    {
        return $this->nullableString($this->value(self::PUBLIC_URL));
    }

    public function save(array $data): void
    {
        $accessKeyId = array_key_exists('access_key_id', $data)
            ? trim((string) $data['access_key_id'])
            : (string) $this->accessKeyId();
        $endpoint = array_key_exists('endpoint', $data)
            ? rtrim(trim((string) $data['endpoint']), '/')
            : (string) $this->endpoint();
        $publicUrl = array_key_exists('public_url', $data)
            ? rtrim(trim((string) $data['public_url']), '/')
            : (string) $this->publicUrl();
        $publicBucket = array_key_exists('public_bucket', $data) || array_key_exists('bucket', $data)
            ? trim((string) ($data['public_bucket'] ?? $data['bucket'] ?? ''))
            : (string) $this->publicBucket();
        $privateBucket = array_key_exists('private_bucket', $data)
            ? trim((string) $data['private_bucket'])
            : (string) $this->privateBucket();
        $privateAccessConfirmed = array_key_exists('private_access_confirmed', $data)
            ? ! empty($data['private_access_confirmed'])
            : $this->privateAccessConfirmed();

        $this->assertBucketsAreIsolated($publicBucket, $privateBucket);
        $this->assertTopologyChangeIsSafe($publicBucket, $privateBucket, $endpoint);

        if ($privateBucket === '') {
            $privateAccessConfirmed = false;
        }

        $enabled = array_key_exists('enabled', $data)
            ? ! empty($data['enabled'])
            : $this->enabled();

        if ($enabled && ! $this->enabled()) {
            $this->assertActivationWasVerified($privateBucket, $privateAccessConfirmed);
        }

        AppSetting::setValue(self::ENABLED, $enabled ? '1' : '0');
        AppSetting::setValue(self::ACCESS_KEY_ID, $accessKeyId);
        AppSetting::setValue(self::ENDPOINT, $endpoint);
        AppSetting::setValue(self::PUBLIC_URL, $publicUrl);
        AppSetting::setValue(self::PUBLIC_BUCKET, $publicBucket);
        AppSetting::setValue(self::BUCKET, $publicBucket);
        AppSetting::setValue(self::PRIVATE_BUCKET, $privateBucket);
        AppSetting::setValue(self::PRIVATE_ACCESS_CONFIRMED, $privateAccessConfirmed ? '1' : '0');

        if (filled($data['secret_access_key'] ?? null)) {
            AppSetting::setValue(
                self::SECRET_ACCESS_KEY,
                trim((string) $data['secret_access_key']),
                encrypted: true,
            );
        }

        $this->forgetCachedSettings();
        $this->configureNamedDisks();
    }

    /** @deprecated This historically meant the public R2 bucket. */
    public function isConfigured(): bool
    {
        return $this->isPublicConfigured();
    }

    public function isPublicConfigured(): bool
    {
        return $this->baseCredentialsConfigured()
            && filled($this->publicBucket())
            && filled($this->publicUrl());
    }

    public function isPrivateConfigured(): bool
    {
        return $this->baseCredentialsConfigured()
            && filled($this->privateBucket())
            && $this->privateAccessConfirmed()
            && $this->bucketsAreIsolated();
    }

    public function bucketsAreIsolated(): bool
    {
        $publicBucket = $this->publicBucket();
        $privateBucket = $this->privateBucket();

        return blank($privateBucket)
            || blank($publicBucket)
            || ! hash_equals(strtolower($publicBucket), strtolower($privateBucket));
    }

    public function publicTopologyLocked(): bool
    {
        return filter_var($this->value(self::PUBLIC_TOPOLOGY_LOCKED, '0'), FILTER_VALIDATE_BOOLEAN);
    }

    public function privateTopologyLocked(): bool
    {
        return filter_var($this->value(self::PRIVATE_TOPOLOGY_LOCKED, '0'), FILTER_VALIDATE_BOOLEAN);
    }

    /** @return array<string, mixed> */
    public function publicDiskConfig(): array
    {
        return $this->s3Config($this->publicBucket(), $this->publicUrl());
    }

    /** @return array<string, mixed> */
    public function privateDiskConfig(): array
    {
        return $this->s3Config($this->privateBucket());
    }

    /** @deprecated Use publicDiskConfig(). */
    public function diskConfig(): array
    {
        return $this->publicDiskConfig();
    }

    /**
     * Hydrate only stable R2 disk names. The local public/local disks retain
     * their configured meaning for legacy fallback and safe migrations.
     */
    public function configureNamedDisks(): void
    {
        $publicConfig = $this->isPublicConfigured()
            ? array_replace($this->baseDiskConfigs['r2_public'], $this->publicDiskConfig())
            : $this->baseDiskConfigs['r2_public'];
        $privateConfig = $this->isPrivateConfigured()
            ? array_replace($this->baseDiskConfigs['r2_private'], $this->privateDiskConfig())
            : $this->baseDiskConfigs['r2_private'];

        Config::set('filesystems.disks.r2', $this->isPublicConfigured()
            ? array_replace($this->baseDiskConfigs['r2'], $this->publicDiskConfig())
            : $this->baseDiskConfigs['r2']);
        Config::set('filesystems.disks.r2_public', $publicConfig);
        Config::set('filesystems.disks.r2_private', $privateConfig);

        Storage::forgetDisk(['r2', 'r2_public', 'r2_private']);

        if ($this->enabled() && $this->isPublicConfigured()) {
            $this->lockTopology(self::PUBLIC_TOPOLOGY_LOCKED);
        }

        if ($this->enabled() && $this->isPrivateConfigured()) {
            $this->lockTopology(self::PRIVATE_TOPOLOGY_LOCKED);
        }
    }

    /** @return array{ok: bool, message: string} */
    public function testConnection(): array
    {
        return $this->testPublicConnection();
    }

    /** @return array{ok: bool, message: string} */
    public function testPublicConnection(): array
    {
        $this->forgetCachedSettings();

        if (! $this->isPublicConfigured()) {
            return [
                'ok' => false,
                'message' => $this->configurationIssueMessage('public'),
            ];
        }

        return $this->roundTrip($this->buildPublicDisk(), 'public', verifyPublicUrl: true);
    }

    /** @return array{ok: bool, message: string} */
    public function testPrivateConnection(): array
    {
        $this->forgetCachedSettings();

        if (! $this->isPrivateConfigured()) {
            return [
                'ok' => false,
                'message' => $this->configurationIssueMessage('private'),
            ];
        }

        return $this->roundTrip($this->buildPrivateDisk(), 'private');
    }

    public function buildPublicDisk(): Filesystem
    {
        return Storage::build($this->publicDiskConfig());
    }

    public function buildPrivateDisk(): Filesystem
    {
        return Storage::build($this->privateDiskConfig());
    }

    /** @deprecated Use buildPublicDisk(). */
    public function buildDisk(): Filesystem
    {
        return $this->buildPublicDisk();
    }

    public function forgetCachedSettings(): void
    {
        $this->cachedValues = [];
        $this->settingsTableAvailable = null;
    }

    protected function secretAccessKey(): ?string
    {
        return $this->nullableString($this->value(self::SECRET_ACCESS_KEY));
    }

    protected function baseCredentialsConfigured(): bool
    {
        return filled($this->accessKeyId())
            && $this->hasSecretAccessKey()
            && filled($this->endpoint());
    }

    /** @return array<string, mixed> */
    protected function s3Config(?string $bucket, ?string $url = null): array
    {
        return array_filter([
            'driver' => 's3',
            'key' => $this->accessKeyId(),
            'secret' => $this->secretAccessKey(),
            'region' => 'auto',
            'bucket' => $bucket,
            'url' => $url,
            'endpoint' => $this->endpoint(),
            'use_path_style_endpoint' => true,
            'throw' => true,
            'report' => false,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /** @return array{ok: bool, message: string} */
    protected function roundTrip(Filesystem $disk, string $scope, bool $verifyPublicUrl = false): array
    {
        $path = '_health/zamzam-r2-'.$scope.'-'.Str::random(8).'.txt';
        $written = false;

        try {
            $written = $disk->put($path, 'ok');
            $readBack = $disk->get($path);

            if (! $written || $readBack !== 'ok') {
                return ['ok' => false, 'message' => 'Connected, but the test object could not be read back correctly.'];
            }

            if ($verifyPublicUrl) {
                $publicResponse = Http::timeout(10)->get($this->publicObjectUrl($path));

                if (! $publicResponse->successful() || $publicResponse->body() !== 'ok') {
                    return [
                        'ok' => false,
                        'message' => 'S3 access works, but the configured public custom domain did not serve the test object. Check its bucket mapping and public-access settings.',
                    ];
                }
            }

            $this->lockTopology($scope === 'public'
                ? self::PUBLIC_TOPOLOGY_LOCKED
                : self::PRIVATE_TOPOLOGY_LOCKED);

            return ['ok' => true, 'message' => 'Connected. A test object was written and read successfully; cleanup was attempted.'];
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => 'Connection failed: '.$exception->getMessage()];
        } finally {
            if ($written) {
                try {
                    $disk->delete($path);
                } catch (Throwable) {
                    // The connection result above remains useful; cleanup can
                    // be retried safely because the marker name is random.
                }
            }
        }
    }

    protected function publicObjectUrl(string $path): string
    {
        $segments = array_map('rawurlencode', explode('/', $path));

        return rtrim((string) $this->publicUrl(), '/').'/'.implode('/', $segments);
    }

    protected function configurationIssueMessage(string $scope): string
    {
        $required = [
            'Access Key ID' => $this->accessKeyId(),
            'Secret Access Key' => $this->secretAccessKey(),
            'S3 endpoint' => $this->endpoint(),
        ];

        if ($scope === 'private') {
            $required['Private bucket name'] = $this->privateBucket();

            if (! $this->privateAccessConfirmed()) {
                $required['Private bucket public-access confirmation'] = null;
            }
        } else {
            $required['Public bucket name'] = $this->publicBucket();
            $required['Public custom-domain URL'] = $this->publicUrl();
        }

        $missing = array_keys(array_filter(
            $required,
            static fn (mixed $value): bool => blank($value),
        ));

        if ($scope === 'private' && ! $this->bucketsAreIsolated()) {
            $missing[] = 'a private bucket name different from the public bucket';
        }

        return $missing === []
            ? 'The saved R2 configuration is incomplete or inconsistent.'
            : 'Complete these R2 fields first: '.implode(', ', $missing).'.';
    }

    protected function value(string $key, mixed $default = null): mixed
    {
        if (! $this->settingsTableExists()) {
            return $default;
        }

        if (! array_key_exists($key, $this->cachedValues)) {
            $this->cachedValues[$key] = AppSetting::getValue($key, $default);
        }

        return $this->cachedValues[$key];
    }

    protected function settingsTableExists(): bool
    {
        return $this->settingsTableAvailable ??= Schema::hasTable('app_settings');
    }

    protected function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function assertBucketsAreIsolated(string $publicBucket, string $privateBucket): void
    {
        if ($publicBucket === '' || $privateBucket === '') {
            return;
        }

        if (hash_equals(strtolower($publicBucket), strtolower($privateBucket))) {
            throw ValidationException::withMessages([
                'private_bucket' => 'The private bucket must be different from the public bucket and must not have public access enabled.',
            ]);
        }
    }

    protected function assertTopologyChangeIsSafe(
        string $publicBucket,
        string $privateBucket,
        string $endpoint,
    ): void {
        $endpointChanged = filled($this->endpoint()) && $endpoint !== $this->endpoint();

        if ($this->publicTopologyLocked()) {
            if ($publicBucket !== (string) $this->publicBucket()) {
                throw ValidationException::withMessages([
                    'public_bucket' => 'The active public bucket is locked. Copy and verify all objects with a planned storage-rotation workflow before switching buckets.',
                ]);
            }

            if ($endpointChanged) {
                throw ValidationException::withMessages([
                    'endpoint' => 'The active R2 endpoint is locked. Stage and verify a storage migration before changing accounts.',
                ]);
            }
        }

        if ($this->privateTopologyLocked()) {
            if ($privateBucket !== (string) $this->privateBucket()) {
                throw ValidationException::withMessages([
                    'private_bucket' => 'The active private bucket is locked. Copy and verify all objects with a planned storage-rotation workflow before switching buckets.',
                ]);
            }

            if ($endpointChanged) {
                throw ValidationException::withMessages([
                    'endpoint' => 'The active R2 endpoint is locked. Stage and verify a storage migration before changing accounts.',
                ]);
            }
        }
    }

    protected function assertActivationWasVerified(string $privateBucket, bool $privateAccessConfirmed): void
    {
        $errors = [];

        if (! $this->publicTopologyLocked()) {
            $errors['enabled'] = 'Test the public bucket and custom domain successfully before enabling R2 uploads.';
        }

        if ($privateBucket !== '' && ! $this->privateTopologyLocked()) {
            $errors['private_bucket'] = 'Test the private bucket successfully before enabling it for private uploads.';
        }

        if ($privateBucket !== '' && ! $privateAccessConfirmed) {
            $errors['private_access_confirmed'] = 'Confirm in Cloudflare that the private bucket has no public r2.dev or custom-domain access.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    protected function lockTopology(string $key): void
    {
        if (filter_var($this->value($key, '0'), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        AppSetting::setValue($key, '1');
        $this->cachedValues[$key] = '1';
    }
}
