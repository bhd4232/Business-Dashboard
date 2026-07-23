<?php

namespace App\Filament\Resources\StorefrontSettings\Pages;

use App\Filament\Concerns\HasStickyHeaderFormActions;
use App\Filament\Resources\StorefrontPages\StorefrontPageResource;
use App\Filament\Resources\StorefrontSettings\StorefrontSettingResource;
use App\Models\Company;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Validation\ValidationException;

class EditStorefrontSetting extends EditRecord
{
    use HasStickyHeaderFormActions;

    protected static string $resource = StorefrontSettingResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->syncCompanyDomain($this->domainSyncData($data));

        unset($data['company_domain'], $data['company_domain_verified']);

        return $data;
    }

    protected function domainSyncData(array $data): array
    {
        $rawState = $this->form->getRawState();

        if ($rawState instanceof Arrayable) {
            $rawState = $rawState->toArray();
        }

        if (! is_array($rawState)) {
            return $data;
        }

        return array_replace($rawState, $data);
    }

    protected function syncCompanyDomain(array $data): void
    {
        $companyId = $data['company_id'] ?? $this->record->company_id;

        $company = Company::withoutGlobalScopes()->find($companyId);

        if (! $company) {
            return;
        }

        $domain = Company::normalizeDomain($data['company_domain'] ?? null);
        $this->assertDomainIsAvailable($domain, (int) $company->getKey());
        $domainChanged = $domain !== Company::normalizeDomain($company->domain);

        $company->forceFill([
            'domain' => $domain,
            'domain_verified' => filled($domain)
                && ! $domainChanged
                && (bool) ($data['company_domain_verified'] ?? false),
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

    protected function getHeaderActions(): array
    {
        return [
            StorefrontSettingResource::syncWooCommerceAction(),
            Action::make('managePages')
                ->label('Manage Pages')
                ->icon('heroicon-o-document-text')
                ->url(StorefrontPageResource::getUrl('index')),
            Action::make('createPage')
                ->label('New Page')
                ->icon('heroicon-o-plus')
                ->url(StorefrontPageResource::getUrl('create')),
            $this->getStickySaveFormAction(),
        ];
    }
}
