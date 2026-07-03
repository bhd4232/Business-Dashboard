<?php

namespace App\Filament\Resources\StorefrontSettings\Pages;

use App\Filament\Resources\StorefrontSettings\StorefrontSettingResource;
use App\Models\Company;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateStorefrontSetting extends CreateRecord
{
    protected static string $resource = StorefrontSettingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->syncCompanyDomain($data);

        unset($data['company_domain'], $data['company_domain_verified']);

        return $data;
    }

    protected function syncCompanyDomain(array $data): void
    {
        if (! isset($data['company_id'])) {
            return;
        }

        $company = Company::withoutGlobalScopes()->find($data['company_id']);

        if (! $company) {
            return;
        }

        $domain = Company::normalizeDomain($data['company_domain'] ?? null);
        $this->assertDomainIsAvailable($domain, (int) $company->getKey());

        $company->forceFill([
            'domain' => $domain,
            'domain_verified' => (bool) ($data['company_domain_verified'] ?? false),
        ])->save();
    }

    protected function assertDomainIsAvailable(?string $domain, int $companyId): void
    {
        if (! $domain) {
            return;
        }

        $exists = Company::withoutGlobalScopes()
            ->where('domain', $domain)
            ->whereKeyNot($companyId)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'data.company_domain' => 'This storefront domain is already assigned to another company.',
            ]);
        }
    }
}
