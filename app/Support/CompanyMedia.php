<?php

namespace App\Support;

use App\Models\Company;
use App\Rules\AccessibleCompany;
use App\Services\CompanyContext;
use App\Services\CompanyStorageService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CompanyMedia
{
    public static function resolve(mixed $record = null, mixed $companyId = null): ?Company
    {
        if (filled($companyId)) {
            return Company::query()->find($companyId);
        }

        if ($record instanceof Company) {
            return $record->exists ? $record : null;
        }

        if ($record instanceof Model && filled($record->getAttribute('company_id'))) {
            return Company::query()->find($record->getAttribute('company_id'));
        }

        $context = app(CompanyContext::class);

        return $context->hasCompany() ? $context->company() : null;
    }

    public static function canResolve(mixed $record = null, mixed $companyId = null): bool
    {
        try {
            return self::resolveForWrite($record, $companyId) instanceof Company;
        } catch (ValidationException) {
            return false;
        }
    }

    public static function require(mixed $record = null, mixed $companyId = null): Company
    {
        return self::resolveForWrite($record, $companyId) ?? throw ValidationException::withMessages([
            'company_id' => 'Select a company before uploading media.',
        ]);
    }

    public static function publicDiskName(): string
    {
        return app(CompanyStorageService::class)->publicDiskName();
    }

    public static function publicDirectory(string $area, mixed $record = null, mixed $companyId = null): string
    {
        return app(CompanyStorageService::class)->publicDirectory(
            self::require($record, $companyId),
            $area,
        );
    }

    public static function publicUrl(?string $path, mixed $record = null, mixed $companyId = null): ?string
    {
        return app(CompanyStorageService::class)->publicUrl(
            $path,
            self::resolve($record, $companyId),
        );
    }

    /**
     * @return array{name: string, size: int, type: string|null, url: string}|null
     */
    public static function publicFileMetadata(string $file, mixed $record = null, mixed $companyId = null): ?array
    {
        $company = self::resolve($record, $companyId);

        if (! $company) {
            return null;
        }

        try {
            $storage = app(CompanyStorageService::class);
            $location = $storage->locatePublic($file, $company);

            if ($location === null) {
                return null;
            }

            $disk = Storage::disk($location['disk']);
            $url = $storage->publicUrl($file, $company);

            if (blank($url)) {
                return null;
            }

            $type = rescue(
                fn (): string|false => $disk->mimeType($location['path']),
                report: false,
            );

            return [
                'name' => basename($location['path']),
                'size' => (int) rescue(
                    fn (): int => $disk->size($location['path']),
                    rescue: 0,
                    report: false,
                ),
                'type' => is_string($type) ? $type : null,
                'url' => $url,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    public static function publicFileMetadataCallback(): Closure
    {
        return static fn (string $file, mixed $record = null): ?array => self::publicFileMetadata($file, $record);
    }

    public static function publicFileUrlCallback(): Closure
    {
        return static fn (string $file, mixed $record = null): ?string => self::publicUrl($file, $record);
    }

    public static function constrainCompanyQuery(Builder $query): Builder
    {
        $user = Auth::user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn(
            $query->qualifyColumn('id'),
            $user->accessibleCompanies()->pluck('companies.id'),
        );
    }

    public static function companyAccessRule(): ValidationRule
    {
        return new AccessibleCompany;
    }

    protected static function resolveForWrite(mixed $record = null, mixed $companyId = null): ?Company
    {
        $company = self::resolve($record, $companyId);

        if (! $company) {
            return null;
        }

        $user = Auth::user();

        if (! $user || ! $user->is_active || ! $user->canAccessCompany((int) $company->getKey())) {
            throw ValidationException::withMessages([
                'company_id' => 'You are not allowed to upload media for the selected company.',
            ]);
        }

        return $company;
    }
}
