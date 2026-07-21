<?php

namespace App\Support;

use App\Models\Company;
use App\Services\CompanyStorageService;

/**
 * Backward-compatible facade for company-aware public storage URLs.
 */
class StorageUrl
{
    public static function for(?string $path, ?Company $company = null): ?string
    {
        return app(CompanyStorageService::class)->publicUrl($path, $company);
    }
}
