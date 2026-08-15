<?php

namespace App\Filament\Resources\MetaAdAccounts\Pages;

use App\Filament\Concerns\HasStickyHeaderFormActions;
use App\Filament\Resources\MetaAdAccounts\MetaAdAccountResource;
use Filament\Resources\Pages\EditRecord;

class EditMetaAdAccount extends EditRecord
{
    use HasStickyHeaderFormActions;

    protected static string $resource = MetaAdAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getStickySaveFormAction(),
        ];
    }

    /**
     * `MetaAdAccount::$hidden` deliberately excludes `credentials` from
     * `attributesToArray()` (Filament's own default fill source — see
     * `EditRecord::fillFormWithDataAndCallHooks()`) so App Secret/Access
     * Token never round-trip to the browser as plaintext. Same fix as
     * `EditConversationChannel`, adapted for `credentials` being one
     * nested array attribute rather than separate flat columns: the
     * non-secret sub-fields are re-included so the form isn't blank on
     * every edit, but the two real secrets stay blank here and are only
     * overwritten on save if the owner actually typed a new value.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $credentials = (array) ($this->getRecord()->credentials ?? []);

        $data['credentials'] = [
            'app_id' => $credentials['app_id'] ?? null,
            'app_secret' => null,
            'access_token' => null,
            'ad_account_id' => $credentials['ad_account_id'] ?? null,
            'page_id' => $credentials['page_id'] ?? null,
        ];

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $existing = (array) ($this->getRecord()->credentials ?? []);

        foreach (['app_secret', 'access_token'] as $secret) {
            if (blank($data['credentials'][$secret] ?? null)) {
                $data['credentials'][$secret] = $existing[$secret] ?? null;
            }
        }

        return $data;
    }
}
