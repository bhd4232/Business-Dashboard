<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Category;
use App\Models\ChatOrderLink;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StorefrontPage;
use App\Models\StorefrontSetting;
use App\Models\StorefrontSlide;
use App\Models\VoucherAttachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Copies legacy unscoped objects into immutable company namespaces.
 *
 * Sources are never deleted. Database paths change only after the copied
 * object has been verified, making the operation resumable after failures.
 */
class CompanyStorageMigrator
{
    public const SCOPES = ['all', 'public', 'private'];

    public function __construct(protected CompanyStorageService $storage) {}

    /**
     * @param  callable(string, string): void|null  $report
     * @return array<string, int>
     */
    public function migrateCompany(
        Company $company,
        bool $execute = false,
        string $scope = 'all',
        ?callable $report = null,
    ): array {
        if (! in_array($scope, self::SCOPES, true)) {
            throw new InvalidArgumentException('Storage migration scope must be all, public, or private.');
        }

        $stats = $this->emptyStats();
        $report ??= static function (): void {};

        if ($scope !== 'private') {
            $this->migratePublicRecords($company, $execute, $stats, $report);
        }

        if ($scope !== 'public') {
            $this->migratePrivateRecords($company, $execute, $stats, $report);
        }

        return $stats;
    }

    /** @return array<string, int> */
    public function emptyStats(): array
    {
        return [
            'planned' => 0,
            'copied' => 0,
            'reused' => 0,
            'updated' => 0,
            'already_scoped' => 0,
            'external' => 0,
            'missing' => 0,
            'conflicts' => 0,
            'errors' => 0,
        ];
    }

    /**
     * @param  array<string, int>  $stats
     * @param  callable(string, string): void  $report
     */
    protected function migratePublicRecords(Company $company, bool $execute, array &$stats, callable $report): void
    {
        $this->migrateScalar($company, $company, 'logo', 'public', 'company', $execute, $stats, $report);

        $settings = (array) $company->settings;
        $oldDarkLogo = $settings['dark_logo'] ?? null;
        $newDarkLogo = $this->migratePath($company, $oldDarkLogo, 'public', 'company', $execute, $stats, $report);

        if ($execute && filled($newDarkLogo) && $newDarkLogo !== $oldDarkLogo) {
            try {
                $settings['dark_logo'] = $newDarkLogo;
                $company->forceFill(['settings' => $settings])->save();
                $stats['updated']++;
            } catch (Throwable $exception) {
                $stats['errors']++;
                $report('error', "Company {$company->id} dark logo path was copied but its database value could not be updated: {$exception->getMessage()}");
            }
        }

        Category::withoutGlobalScopes()
            ->where('company_id', $company->getKey())
            ->orderBy('id')
            ->chunkById(100, function ($records) use ($company, $execute, &$stats, $report): void {
                foreach ($records as $record) {
                    $this->migrateScalar($company, $record, 'image', 'public', 'categories', $execute, $stats, $report);
                }
            });

        Product::withoutGlobalScopes()
            ->where('company_id', $company->getKey())
            ->orderBy('id')
            ->chunkById(100, function ($records) use ($company, $execute, &$stats, $report): void {
                foreach ($records as $record) {
                    $this->migrateScalar($company, $record, 'image', 'public', 'products', $execute, $stats, $report);
                    $this->migrateArray($company, $record, 'gallery_images', 'public', 'products/gallery', $execute, $stats, $report);
                }
            });

        ProductVariant::withoutGlobalScopes()
            ->where('company_id', $company->getKey())
            ->orderBy('id')
            ->chunkById(100, function ($records) use ($company, $execute, &$stats, $report): void {
                foreach ($records as $record) {
                    $this->migrateArray($company, $record, 'images', 'public', 'products/variants', $execute, $stats, $report);
                }
            });

        StorefrontSetting::withoutGlobalScopes()
            ->where('company_id', $company->getKey())
            ->orderBy('id')
            ->chunkById(100, function ($records) use ($company, $execute, &$stats, $report): void {
                foreach ($records as $record) {
                    $this->migrateScalar($company, $record, 'logo', 'public', 'storefront/logos', $execute, $stats, $report);
                    $this->migrateScalar($company, $record, 'logo_dark', 'public', 'storefront/logos', $execute, $stats, $report);
                }
            });

        StorefrontSlide::withoutGlobalScopes()
            ->where('company_id', $company->getKey())
            ->orderBy('id')
            ->chunkById(100, function ($records) use ($company, $execute, &$stats, $report): void {
                foreach ($records as $record) {
                    $this->migrateScalar($company, $record, 'image', 'public', 'storefront/slides', $execute, $stats, $report);
                    $this->migrateScalar($company, $record, 'image_mobile', 'public', 'storefront/slides', $execute, $stats, $report);
                }
            });

        StorefrontPage::withoutGlobalScopes()
            ->where('company_id', $company->getKey())
            ->orderBy('id')
            ->chunkById(100, function ($records) use ($company, $execute, &$stats, $report): void {
                foreach ($records as $record) {
                    $this->migrateScalar($company, $record, 'cover_image', 'public', 'storefront/pages', $execute, $stats, $report);
                }
            });

        ChatOrderLink::withoutGlobalScopes()
            ->where('company_id', $company->getKey())
            ->orderBy('id')
            ->chunkById(100, function ($records) use ($company, $execute, &$stats, $report): void {
                foreach ($records as $record) {
                    $this->migrateChatOrderLinkImages($company, $record, $execute, $stats, $report);
                }
            });

        $this->migrateGlobalBrandingFallbacks($company, $execute, $stats, $report);
    }

    /**
     * @param  array<string, int>  $stats
     * @param  callable(string, string): void  $report
     */
    protected function migratePrivateRecords(Company $company, bool $execute, array &$stats, callable $report): void
    {
        $conversationIds = Conversation::withoutGlobalScopes()
            ->where('company_id', $company->getKey())
            ->select('id');

        ConversationMessage::query()
            ->whereIn('conversation_id', $conversationIds)
            ->orderBy('id')
            ->chunkById(100, function ($records) use ($company, $execute, &$stats, $report): void {
                foreach ($records as $record) {
                    $this->migrateScalar($company, $record, 'media_path', 'private', 'conversations', $execute, $stats, $report);
                }
            });

        VoucherAttachment::withoutGlobalScopes()
            ->where('company_id', $company->getKey())
            ->orderBy('id')
            ->chunkById(100, function ($records) use ($company, $execute, &$stats, $report): void {
                foreach ($records as $record) {
                    $this->migrateScalar($company, $record, 'file_path', 'private', 'voucher-attachments', $execute, $stats, $report);
                }
            });
    }

    /**
     * @param  array<string, int>  $stats
     * @param  callable(string, string): void  $report
     */
    protected function migrateChatOrderLinkImages(
        Company $company,
        ChatOrderLink $link,
        bool $execute,
        array &$stats,
        callable $report,
    ): void {
        $oldPrefill = (array) $link->prefill;
        $newPrefill = $oldPrefill;

        foreach ((array) ($oldPrefill['items'] ?? []) as $index => $item) {
            if (! is_array($item) || ! is_string($item['image'] ?? null)) {
                continue;
            }

            $newPrefill['items'][$index]['image'] = $this->migratePath(
                $company,
                $item['image'],
                'public',
                'products',
                $execute,
                $stats,
                $report,
            ) ?? $item['image'];
        }

        if (! $execute || $newPrefill === $oldPrefill) {
            return;
        }

        try {
            $link->forceFill(['prefill' => $newPrefill])->save();
            $stats['updated'] += collect($newPrefill['items'] ?? [])
                ->filter(fn (mixed $item, int $index): bool => is_array($item)
                    && ($item['image'] ?? null) !== data_get($oldPrefill, "items.{$index}.image"))
                ->count();
        } catch (Throwable $exception) {
            $stats['errors']++;
            $report('error', "Chat order link {$link->getKey()} images were copied but its prefill could not be updated: {$exception->getMessage()}");
        }
    }

    /**
     * Keep the legacy global branding fallback in sync with the oldest
     * company that historically owned those pre-multi-company settings.
     *
     * @param  array<string, int>  $stats
     * @param  callable(string, string): void  $report
     */
    protected function migrateGlobalBrandingFallbacks(
        Company $company,
        bool $execute,
        array &$stats,
        callable $report,
    ): void {
        if ((int) Company::query()->min('id') !== (int) $company->getKey()) {
            return;
        }

        foreach ([CompanySettingsService::LOGO, CompanySettingsService::DARK_LOGO] as $key) {
            $oldPath = AppSetting::getValue($key);
            $newPath = $this->migratePath($company, is_string($oldPath) ? $oldPath : null, 'public', 'company', $execute, $stats, $report);

            if (! $execute || blank($newPath) || $newPath === $oldPath) {
                continue;
            }

            try {
                AppSetting::setValue($key, $newPath);
                $stats['updated']++;
            } catch (Throwable $exception) {
                $stats['errors']++;
                $report('error', "Global branding setting {$key} could not be updated: {$exception->getMessage()}");
            }
        }
    }

    /**
     * @param  array<string, int>  $stats
     * @param  callable(string, string): void  $report
     */
    protected function migrateScalar(
        Company $company,
        Model $record,
        string $field,
        string $scope,
        string $fallbackArea,
        bool $execute,
        array &$stats,
        callable $report,
    ): void {
        $oldPath = $record->getAttribute($field);
        $newPath = $this->migratePath($company, is_string($oldPath) ? $oldPath : null, $scope, $fallbackArea, $execute, $stats, $report);

        if (! $execute || blank($newPath) || $newPath === $oldPath) {
            return;
        }

        try {
            $record->forceFill([$field => $newPath])->save();
            $stats['updated']++;
        } catch (Throwable $exception) {
            $stats['errors']++;
            $report('error', class_basename($record).' '.$record->getKey()." path was copied but {$field} could not be updated: {$exception->getMessage()}");
        }
    }

    /**
     * @param  array<string, int>  $stats
     * @param  callable(string, string): void  $report
     */
    protected function migrateArray(
        Company $company,
        Model $record,
        string $field,
        string $scope,
        string $fallbackArea,
        bool $execute,
        array &$stats,
        callable $report,
    ): void {
        $oldPaths = array_values((array) $record->getAttribute($field));

        if ($oldPaths === []) {
            return;
        }

        $newPaths = [];

        foreach ($oldPaths as $oldPath) {
            if (! is_string($oldPath) || blank($oldPath)) {
                $newPaths[] = $oldPath;

                continue;
            }

            $newPaths[] = $this->migratePath($company, $oldPath, $scope, $fallbackArea, $execute, $stats, $report) ?? $oldPath;
        }

        if (! $execute || $newPaths === $oldPaths) {
            return;
        }

        try {
            $record->forceFill([$field => $newPaths])->save();
            $stats['updated'] += collect($newPaths)
                ->filter(fn (mixed $newPath, int $index): bool => $newPath !== ($oldPaths[$index] ?? null))
                ->count();
        } catch (Throwable $exception) {
            $stats['errors']++;
            $report('error', class_basename($record).' '.$record->getKey()." files were copied but {$field} could not be updated: {$exception->getMessage()}");
        }
    }

    /**
     * @param  array<string, int>  $stats
     * @param  callable(string, string): void  $report
     */
    protected function migratePath(
        Company $company,
        ?string $path,
        string $scope,
        string $fallbackArea,
        bool $execute,
        array &$stats,
        callable $report,
    ): ?string {
        if (blank($path)) {
            return $path;
        }

        $path = trim((string) $path);

        if ($this->isExternalReference($path)) {
            $stats['external']++;

            return $path;
        }

        $isScoped = str_starts_with($path, 'companies/');

        try {
            $targetDisk = $scope === 'public'
                ? $this->storage->publicDiskName()
                : $this->storage->privateDiskName();

            if ($isScoped) {
                $location = $scope === 'public'
                    ? $this->storage->locatePublic($path, $company)
                    : $this->storage->locatePrivate($path, $company);

                if ($location === null) {
                    $stats['missing']++;
                    $report('warning', "Scoped {$scope} object is missing: {$path}");

                    return $path;
                }

                if ($location['disk'] === $targetDisk) {
                    $stats['already_scoped']++;

                    return $path;
                }

                $source = $location;
                $targetPath = $path;
                $area = $this->scopedArea($company, $path, $scope);
            } else {
                $source = $scope === 'public'
                    ? $this->storage->locateLegacyPublic($path)
                    : $this->storage->locateLegacyPrivate($path, $company);

                if ($source === null) {
                    $stats['missing']++;
                    $report('warning', "Legacy {$scope} object is missing: {$path}");

                    return $path;
                }

                $area = $this->legacyArea($path, $fallbackArea);
                $targetPath = ($scope === 'public'
                    ? $this->storage->publicDirectory($company, $area)
                    : $this->storage->privateDirectory($company, $area))
                    .'/'.basename($path);
            }

            $sourceHash = $this->hashLocation($source['disk'], $source['path']);
            $target = Storage::disk($targetDisk);

            if ($target->exists($targetPath)) {
                if (! hash_equals($sourceHash, $this->hashLocation($targetDisk, $targetPath))) {
                    $stats['conflicts']++;
                    $report('error', "Refusing conflicting destination object: {$targetDisk}:{$targetPath}");

                    return $path;
                }

                if ($execute) {
                    $stats['reused']++;
                }
            }

            $stats['planned']++;

            if (! $execute) {
                $report('info', "Would migrate {$scope} object {$path} -> {$targetPath}");

                return $targetPath;
            }

            if (! $target->exists($targetPath)) {
                $copiedPath = $scope === 'public'
                    ? $this->storage->copyPublicToActive($company, $path, $area)
                    : $this->storage->copyPrivateToActive($company, $path, $area);

                if ($copiedPath !== $targetPath) {
                    throw new \RuntimeException("Unexpected destination path [{$copiedPath}].");
                }

                $stats['copied']++;
            }

            if (! $target->exists($targetPath) || ! hash_equals($sourceHash, $this->hashLocation($targetDisk, $targetPath))) {
                throw new \RuntimeException("Copied object verification failed for [{$targetDisk}:{$targetPath}].");
            }

            if ($scope === 'public' && $targetDisk === 'r2_public') {
                $this->storage->markPublicR2Preferred($company, $targetPath, $sourceHash);
            }

            $report('info', "Migrated {$scope} object {$path} -> {$targetPath}");

            return $targetPath;
        } catch (Throwable $exception) {
            $stats['errors']++;
            $report('error', "Could not migrate {$scope} object {$path}: {$exception->getMessage()}");

            return $path;
        }
    }

    protected function scopedArea(Company $company, string $path, string $scope): string
    {
        $prefix = $company->storageRoot()."/{$scope}/";

        if (! str_starts_with($path, $prefix)) {
            throw new InvalidArgumentException('The scoped storage path does not belong to the selected company and scope.');
        }

        $relativePath = substr($path, strlen($prefix));
        $directory = dirname($relativePath);

        return $directory === '.' ? '_root' : $directory;
    }

    protected function isExternalReference(string $path): bool
    {
        if (str_starts_with($path, '/') && ! str_starts_with($path, '//')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($path, PHP_URL_SCHEME));

        return filter_var($path, FILTER_VALIDATE_URL) !== false
            && in_array($scheme, ['http', 'https'], true);
    }

    protected function legacyArea(string $path, string $fallback): string
    {
        $directory = trim(str_replace('\\', '/', dirname($path)), './');

        return $directory !== '' ? $directory : $fallback;
    }

    protected function hashLocation(string $disk, string $path): string
    {
        $stream = Storage::disk($disk)->readStream($path);

        if (! is_resource($stream)) {
            throw new \RuntimeException("Unable to read storage object [{$disk}:{$path}].");
        }

        $hash = hash_init('sha256');

        try {
            while (! feof($stream)) {
                $chunk = fread($stream, 1024 * 1024);

                if ($chunk === false) {
                    throw new \RuntimeException("Unable to hash storage object [{$disk}:{$path}].");
                }

                hash_update($hash, $chunk);
            }
        } finally {
            fclose($stream);
        }

        return hash_final($hash);
    }
}
