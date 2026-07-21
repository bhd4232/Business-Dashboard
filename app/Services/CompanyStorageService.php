<?php

namespace App\Services;

use App\Models\Company;
use App\Models\LegacyPrivateStoragePath;
use DateTimeInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Company-isolated public and private object storage.
 *
 * Disk selection is global, but every new object path is rooted beneath an
 * immutable company storage key. The stable local disks remain available so
 * callers can locate and safely copy legacy, unscoped objects after R2 is
 * enabled.
 */
class CompanyStorageService
{
    /** @var array<string, array{disk: string, path: string}|null> */
    protected array $locationCache = [];

    /** @var array<string, bool> */
    protected array $publicR2PreferenceCache = [];

    public function __construct(
        protected StorageSettingsService $settings,
        protected CompanyContext $companyContext,
    ) {}

    public function publicDiskName(): string
    {
        return $this->settings->enabled() && $this->settings->isPublicConfigured()
            ? 'r2_public'
            : 'public';
    }

    public function privateDiskName(): string
    {
        return $this->settings->enabled() && $this->settings->isPrivateConfigured()
            ? 'r2_private'
            : 'local';
    }

    public function publicDirectory(Company $company, string $area): string
    {
        return $company->storageRoot().'/public/'.$this->safePath($area, 'area');
    }

    public function privateDirectory(Company $company, string $area): string
    {
        return $company->storageRoot().'/private/'.$this->safePath($area, 'area');
    }

    public function publicUrl(?string $path, ?Company $company = null): ?string
    {
        if (blank($path)) {
            return null;
        }

        $path = trim((string) $path);

        if ($this->isAbsolutePublicUrl($path)) {
            return $path;
        }

        $company = $company ?? $this->companyContext->company();
        $safePath = $this->safePath($path);
        $this->assertCompanyOwnsScopedPath($safePath, $company, 'public');

        if ($company
            && $this->settings->enabled()
            && $this->settings->isPublicConfigured()
            && $this->isScopedPath($safePath)
            && $this->prefersPublicR2($company, $safePath)) {
            return Storage::disk('r2_public')->url($safePath);
        }

        // URL generation must not issue one S3 HEAD request per storefront
        // image. Prefer an exact local object when it exists; otherwise an
        // R2 custom-domain URL is deterministic and remains available even
        // when the S3 API endpoint is temporarily degraded.
        try {
            if (Storage::disk('public')->exists($safePath)) {
                return Storage::disk('public')->url($safePath);
            }
        } catch (Throwable) {
            // A local disk failure should not prevent an otherwise valid CDN
            // URL from being rendered.
        }

        if ($this->settings->isPublicConfigured()) {
            return Storage::disk('r2_public')->url($safePath);
        }

        return Storage::disk('public')->url($safePath);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function privateTemporaryUrl(
        ?string $path,
        Company $company,
        DateTimeInterface $expiration,
        array $options = [],
    ): ?string {
        if (blank($path)) {
            return null;
        }

        $location = $this->locatePrivate((string) $path, $company);

        if ($location === null) {
            return null;
        }

        return Storage::disk($location['disk'])->temporaryUrl(
            $location['path'],
            $expiration,
            $options,
        );
    }

    /**
     * @param  resource|string  $contents
     * @param  array<string, mixed>  $options
     */
    public function putPublic(
        Company $company,
        string $area,
        string $filename,
        mixed $contents,
        array $options = [],
    ): string {
        $path = $this->publicDirectory($company, $area).'/'.$this->safeFilename($filename);
        $diskName = $this->publicDiskName();
        $options = $this->writeOptions($diskName, 'public', $options);

        $this->putOrFail(Storage::disk($diskName), $path, $contents, $options);
        $this->forgetLocations();

        return $path;
    }

    /**
     * @param  resource|string  $contents
     * @param  array<string, mixed>  $options
     */
    public function putPrivate(
        Company $company,
        string $area,
        string $filename,
        mixed $contents,
        array $options = [],
    ): string {
        $path = $this->privateDirectory($company, $area).'/'.$this->safeFilename($filename);
        $diskName = $this->privateDiskName();
        $options = $this->writeOptions($diskName, 'private', $options);

        $this->putOrFail(Storage::disk($diskName), $path, $contents, $options);
        $this->forgetLocations();

        return $path;
    }

    /**
     * Locate a public object on its active disk or a stable legacy disk.
     *
     * @return array{disk: string, path: string}|null
     */
    public function locatePublic(?string $path, ?Company $company = null): ?array
    {
        if (blank($path)) {
            return null;
        }

        $company = $company ?? $this->companyContext->company();
        $safePath = $this->safePath((string) $path);
        $this->assertCompanyOwnsScopedPath($safePath, $company, 'public');

        return $this->locate(
            'public',
            $safePath,
            $company,
            $this->publicDiskName(),
            'public',
            $this->settings->isPublicConfigured() ? 'r2_public' : null,
        );
    }

    /**
     * Locate a private object on its active disk or the stable local disk.
     *
     * @return array{disk: string, path: string}|null
     */
    public function locatePrivate(?string $path, Company $company): ?array
    {
        if (blank($path)) {
            return null;
        }

        $safePath = $this->safePath((string) $path);
        $this->assertCompanyOwnsScopedPath($safePath, $company, 'private');

        if (! $this->isScopedPath($safePath)) {
            $this->assertLegacyPrivatePathOwnership($safePath, $company);
        }

        return $this->locate(
            'private',
            $safePath,
            $company,
            $this->privateDiskName(),
            'local',
            $this->settings->isPrivateConfigured() ? 'r2_private' : null,
        );
    }

    /**
     * Locate only the exact legacy, unscoped public key. The stable local
     * public disk is checked before the configured R2 public disk.
     *
     * @return array{disk: string, path: string}|null
     */
    public function locateLegacyPublic(string $path): ?array
    {
        return $this->locateLegacy(
            $path,
            'public',
            $this->settings->isPublicConfigured() ? 'r2_public' : null,
        );
    }

    /**
     * Locate only the exact legacy, unscoped private key. The stable local
     * private disk is checked before the configured R2 private disk.
     *
     * @return array{disk: string, path: string}|null
     */
    public function locateLegacyPrivate(string $path, Company $company): ?array
    {
        $this->assertLegacyPrivatePathOwnership($this->safePath($path), $company);

        return $this->locateLegacy(
            $path,
            'local',
            $this->settings->isPrivateConfigured() ? 'r2_private' : null,
        );
    }

    public function readPublic(?string $path, ?Company $company = null): ?string
    {
        $location = $this->locatePublic($path, $company);

        return $location === null
            ? null
            : Storage::disk($location['disk'])->get($location['path']);
    }

    public function readPrivate(?string $path, Company $company): ?string
    {
        $location = $this->locatePrivate($path, $company);

        return $location === null
            ? null
            : Storage::disk($location['disk'])->get($location['path']);
    }

    public function readLegacyPublic(string $path): ?string
    {
        $location = $this->locateLegacyPublic($path);

        return $location === null
            ? null
            : Storage::disk($location['disk'])->get($location['path']);
    }

    public function readLegacyPrivate(string $path, Company $company): ?string
    {
        $location = $this->locateLegacyPrivate($path, $company);

        return $location === null
            ? null
            : Storage::disk($location['disk'])->get($location['path']);
    }

    public function copyPublicToActive(
        Company $company,
        string $sourcePath,
        string $area,
        ?string $filename = null,
        bool $overwrite = false,
    ): ?string {
        $safeSourcePath = $this->safePath($sourcePath);
        $source = $this->isScopedPath($safeSourcePath)
            ? $this->locatePublic($safeSourcePath, $company)
            : $this->locateLegacyPublic($safeSourcePath);

        if ($source === null) {
            return null;
        }

        return $this->copyToActive(
            $source,
            $this->publicDiskName(),
            $this->publicDirectory($company, $area),
            $filename ?? basename($source['path']),
            'public',
            $overwrite,
        );
    }

    public function copyPrivateToActive(
        Company $company,
        string $sourcePath,
        string $area,
        ?string $filename = null,
        bool $overwrite = false,
    ): ?string {
        $safeSourcePath = $this->safePath($sourcePath);
        $source = $this->isScopedPath($safeSourcePath)
            ? $this->locatePrivate($safeSourcePath, $company)
            : $this->locateLegacyPrivate($safeSourcePath, $company);

        if ($source === null) {
            return null;
        }

        return $this->copyToActive(
            $source,
            $this->privateDiskName(),
            $this->privateDirectory($company, $area),
            $filename ?? basename($source['path']),
            'private',
            $overwrite,
        );
    }

    public function forgetLocations(): void
    {
        $this->locationCache = [];
        $this->publicR2PreferenceCache = [];
    }

    public function markPublicR2Preferred(Company $company, string $path, string $checksum): void
    {
        $safePath = $this->safePath($path);
        $this->assertCompanyOwnsScopedPath($safePath, $company, 'public');

        if (! $this->isScopedPath($safePath)) {
            throw new InvalidArgumentException('Only company-scoped public paths can prefer R2.');
        }

        if (preg_match('/^[a-f0-9]{64}$/', $checksum) !== 1) {
            throw new InvalidArgumentException('A verified SHA-256 checksum is required for an R2 preference marker.');
        }

        $markerPath = $this->publicR2MarkerPath($safePath);
        $payload = json_encode([
            'path' => $safePath,
            'company_storage_key' => (string) $company->storage_key,
            'disk' => 'r2_public',
            'sha256' => $checksum,
            'verified_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR);

        $this->putOrFail(Storage::disk('local'), $markerPath, $payload, []);
        $this->publicR2PreferenceCache[$safePath] = true;
    }

    /**
     * @return array{disk: string, path: string}|null
     */
    protected function locate(
        string $scope,
        string $path,
        ?Company $company,
        string $activeDisk,
        string $legacyDisk,
        ?string $configuredDisk,
    ): ?array {
        $cacheKey = implode('|', [
            $scope,
            $activeDisk,
            $legacyDisk,
            $configuredDisk ?? '-',
            $company?->storage_key ?? '-',
            $path,
        ]);

        if (array_key_exists($cacheKey, $this->locationCache)) {
            return $this->locationCache[$cacheKey];
        }

        $candidates = [];

        if ($this->isScopedPath($path)) {
            $candidates[] = [$activeDisk, $path];

            if ($legacyDisk !== $activeDisk) {
                $candidates[] = [$legacyDisk, $path];
            }

            if ($configuredDisk !== null) {
                $candidates[] = [$configuredDisk, $path];
            }
        } else {
            if ($company !== null) {
                $scopedPath = ($scope === 'public'
                    ? $this->publicDirectory($company, dirname($path) === '.' ? '_legacy' : dirname($path))
                    : $this->privateDirectory($company, dirname($path) === '.' ? '_legacy' : dirname($path)))
                    .'/'.basename($path);

                $candidates[] = [$activeDisk, $scopedPath];

                if ($legacyDisk !== $activeDisk) {
                    $candidates[] = [$legacyDisk, $scopedPath];
                }

                if ($configuredDisk !== null) {
                    $candidates[] = [$configuredDisk, $scopedPath];
                }
            }

            // Existing database paths were unscoped and lived on these
            // stable disks. Check them before a legacy unscoped cloud key.
            $candidates[] = [$legacyDisk, $path];

            if ($activeDisk !== $legacyDisk) {
                $candidates[] = [$activeDisk, $path];
            }

            if ($configuredDisk !== null) {
                $candidates[] = [$configuredDisk, $path];
            }
        }

        foreach ($this->uniqueCandidates($candidates) as [$disk, $candidatePath]) {
            try {
                if (Storage::disk($disk)->exists($candidatePath)) {
                    return $this->locationCache[$cacheKey] = [
                        'disk' => $disk,
                        'path' => $candidatePath,
                    ];
                }
            } catch (Throwable) {
                // Continue to a stable fallback disk. Callers receive null if
                // every candidate is unavailable instead of a storage outage
                // turning a storefront request into a 500 response.
            }
        }

        return $this->locationCache[$cacheKey] = null;
    }

    /**
     * @return array{disk: string, path: string}|null
     */
    protected function locateLegacy(string $path, string $stableDisk, ?string $configuredDisk): ?array
    {
        $safePath = $this->safePath($path);

        if ($this->isScopedPath($safePath)) {
            throw new InvalidArgumentException('Legacy storage paths must be unscoped.');
        }

        $cacheKey = implode('|', ['legacy', $stableDisk, $configuredDisk ?? '-', $safePath]);

        if (array_key_exists($cacheKey, $this->locationCache)) {
            return $this->locationCache[$cacheKey];
        }

        $locations = [];

        foreach ($this->uniqueCandidates([
            [$stableDisk, $safePath],
            ...($configuredDisk === null ? [] : [[$configuredDisk, $safePath]]),
        ]) as [$disk, $candidatePath]) {
            try {
                if (Storage::disk($disk)->exists($candidatePath)) {
                    $locations[] = [
                        'disk' => $disk,
                        'path' => $candidatePath,
                    ];
                }
            } catch (Throwable $exception) {
                throw new RuntimeException(
                    "Unable to inspect legacy storage object [{$disk}:{$candidatePath}].",
                    previous: $exception,
                );
            }
        }

        if (count($locations) > 1) {
            $firstHash = $this->hashLocation($locations[0]['disk'], $locations[0]['path']);

            foreach (array_slice($locations, 1) as $location) {
                if (! hash_equals($firstHash, $this->hashLocation($location['disk'], $location['path']))) {
                    throw new RuntimeException(
                        "Ambiguous legacy object [{$safePath}] has different contents on local and R2 storage.",
                    );
                }
            }
        }

        if ($locations !== []) {
            return $this->locationCache[$cacheKey] = [
                'disk' => $locations[0]['disk'],
                'path' => $locations[0]['path'],
            ];
        }

        return $this->locationCache[$cacheKey] = null;
    }

    protected function hashLocation(string $disk, string $path): string
    {
        $stream = Storage::disk($disk)->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException("Unable to read storage object [{$disk}:{$path}].");
        }

        $hash = hash_init('sha256');

        try {
            while (! feof($stream)) {
                $chunk = fread($stream, 1024 * 1024);

                if ($chunk === false) {
                    throw new RuntimeException("Unable to hash storage object [{$disk}:{$path}].");
                }

                hash_update($hash, $chunk);
            }
        } finally {
            fclose($stream);
        }

        return hash_final($hash);
    }

    protected function prefersPublicR2(Company $company, string $path): bool
    {
        if (array_key_exists($path, $this->publicR2PreferenceCache)) {
            return $this->publicR2PreferenceCache[$path];
        }

        try {
            $marker = Storage::disk('local')->get($this->publicR2MarkerPath($path));
            $payload = json_decode($marker, true, flags: JSON_THROW_ON_ERROR);
            $preferred = is_array($payload)
                && ($payload['path'] ?? null) === $path
                && ($payload['company_storage_key'] ?? null) === (string) $company->storage_key
                && ($payload['disk'] ?? null) === 'r2_public';
        } catch (Throwable) {
            $preferred = false;
        }

        return $this->publicR2PreferenceCache[$path] = $preferred;
    }

    protected function publicR2MarkerPath(string $path): string
    {
        return '_company-storage/public-r2-manifest/'.hash('sha256', $path).'.json';
    }

    /**
     * @param  array{disk: string, path: string}  $source
     */
    protected function copyToActive(
        array $source,
        string $destinationDisk,
        string $destinationDirectory,
        string $filename,
        string $visibility,
        bool $overwrite,
    ): string {
        $destinationPath = $destinationDirectory.'/'.$this->safeFilename($filename);

        if ($source['disk'] === $destinationDisk && $source['path'] === $destinationPath) {
            return $destinationPath;
        }

        $destination = Storage::disk($destinationDisk);

        if (! $overwrite && $destination->exists($destinationPath)) {
            throw new RuntimeException("Refusing to overwrite existing object [{$destinationDisk}:{$destinationPath}].");
        }

        $stream = Storage::disk($source['disk'])->readStream($source['path']);

        if (! is_resource($stream)) {
            throw new RuntimeException("Unable to read source object [{$source['disk']}:{$source['path']}].");
        }

        try {
            $this->putOrFail(
                $destination,
                $destinationPath,
                $stream,
                $this->writeOptions($destinationDisk, $visibility),
            );
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $this->forgetLocations();

        return $destinationPath;
    }

    /**
     * @param  resource|string  $contents
     * @param  array<string, mixed>  $options
     */
    protected function putOrFail(Filesystem $disk, string $path, mixed $contents, array $options): void
    {
        if (! $disk->put($path, $contents, $options)) {
            throw new RuntimeException("Unable to write storage object [{$path}].");
        }
    }

    /**
     * Cloudflare R2 does not implement S3 object ACLs. Public/private access
     * is therefore controlled at bucket level; only local disks receive a
     * Flysystem visibility option.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function writeOptions(string $diskName, string $localVisibility, array $options = []): array
    {
        if (in_array($diskName, ['r2', 'r2_public', 'r2_private'], true)) {
            unset($options['visibility']);

            return $options;
        }

        return [...$options, 'visibility' => $localVisibility];
    }

    protected function assertCompanyOwnsScopedPath(string $path, ?Company $company, string $scope): void
    {
        if (! $this->isScopedPath($path)) {
            return;
        }

        $segments = explode('/', $path);

        if (count($segments) < 4 || ! Str::isUuid($segments[1]) || $segments[2] !== $scope) {
            throw new InvalidArgumentException('The storage path does not belong to the requested storage scope.');
        }

        if ($company !== null && $segments[1] !== (string) $company->storage_key) {
            throw new InvalidArgumentException('The storage path does not belong to the selected company and scope.');
        }
    }

    protected function assertLegacyPrivatePathOwnership(string $path, Company $company): void
    {
        if (! LegacyPrivateStoragePath::allows($path, (int) $company->getKey())) {
            throw new InvalidArgumentException('The legacy private storage path is not registered to the selected company.');
        }
    }

    protected function isScopedPath(string $path): bool
    {
        return str_starts_with($path, 'companies/');
    }

    protected function isAbsolutePublicUrl(string $path): bool
    {
        return filter_var($path, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($path, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    protected function safeFilename(string $filename): string
    {
        $filename = $this->safePath($filename, 'filename');

        if (str_contains($filename, '/')) {
            throw new InvalidArgumentException('Storage filename must not contain directory separators.');
        }

        return $filename;
    }

    protected function safePath(string $path, string $label = 'path'): string
    {
        $path = trim($path);

        if ($path === '') {
            throw new InvalidArgumentException("Storage {$label} must not be empty.");
        }

        if (str_starts_with($path, '/') || str_contains($path, '\\')) {
            throw new InvalidArgumentException("Storage {$label} must be a relative forward-slash path.");
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            throw new InvalidArgumentException("Storage {$label} contains control characters.");
        }

        $segments = explode('/', $path);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new InvalidArgumentException("Storage {$label} contains an unsafe path segment.");
            }
        }

        return implode('/', $segments);
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $candidates
     * @return array<int, array{0: string, 1: string}>
     */
    protected function uniqueCandidates(array $candidates): array
    {
        $unique = [];

        foreach ($candidates as $candidate) {
            $unique[$candidate[0].'|'.$candidate[1]] = $candidate;
        }

        return array_values($unique);
    }
}
