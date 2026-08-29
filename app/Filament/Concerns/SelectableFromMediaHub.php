<?php

namespace App\Filament\Concerns;

use App\Models\Company;
use App\Models\Media;
use App\Support\CompanyMedia;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Drop-in "Select From Media" hint action for any image FileUpload field —
 * lets the user pick an already-uploaded image from the company's Media Hub
 * instead of uploading a new one. The FileUpload field itself is untouched:
 * every existing option, helper text, disk/directory resolution, and image
 * editor keeps working exactly as before.
 *
 * Usage:
 *   FileUpload::make('image')
 *       ->image()
 *       ->hintAction(static::selectFromMediaHubAction()),
 *
 * The company is resolved once, at the action's own top-level evaluation
 * (the same `$get`/`$record` injection every other closure on these fields
 * already relies on — see App\Support\CompanyMedia), then closed over by
 * the modal's own fields. A field whose company isn't simply "the record's
 * own company_id" (a Livewire Page with no bound record, for example) can
 * pass a resolver instead:
 *   ->hintAction(static::selectFromMediaHubAction(fn (): Company => $this->selectedCompany())),
 */
trait SelectableFromMediaHub
{
    /**
     * @param  (Closure(Get $get, mixed $record): ?Company)|null  $companyResolver
     */
    protected static function selectFromMediaHubAction(?Closure $companyResolver = null): Action
    {
        return Action::make('selectFromMediaHub')
            ->label('Select From Media')
            ->icon(Heroicon::OutlinedPhoto)
            ->color('gray')
            ->modalHeading('Select From Media')
            ->modalDescription('Pick a previously uploaded image for this company. New images can also be added from the Media Hub page.')
            ->modalSubmitActionLabel('Use This Image')
            ->disabled(fn (Get $get, mixed $record): bool => ! static::resolveMediaHubCompany($companyResolver, $get, $record))
            ->schema(function (Get $get, mixed $record) use ($companyResolver): array {
                $company = static::resolveMediaHubCompany($companyResolver, $get, $record);

                if (! $company) {
                    return [
                        Placeholder::make('media_hub_unavailable')
                            ->label('')
                            ->content('Select a company before choosing a media image.'),
                    ];
                }

                return [
                    Select::make('media_id')
                        ->label('Image')
                        ->required()
                        ->searchable()
                        ->native(false)
                        ->allowHtml()
                        ->live()
                        ->options(fn (): array => static::mediaHubOptionsForCompany($company, ''))
                        ->getSearchResultsUsing(fn (string $search): array => static::mediaHubOptionsForCompany($company, $search))
                        ->getOptionLabelUsing(fn (mixed $value): ?string => static::mediaHubOptionLabel(
                            Media::withoutGlobalScopes()->where('company_id', $company->getKey())->find($value)
                        ))
                        ->columnSpanFull(),
                    Placeholder::make('media_hub_preview')
                        ->label('')
                        ->content(function (Get $get) use ($company): ?HtmlString {
                            $media = filled($get('media_id'))
                                ? Media::withoutGlobalScopes()->where('company_id', $company->getKey())->find($get('media_id'))
                                : null;

                            if (! $media) {
                                return null;
                            }

                            $url = e((string) CompanyMedia::filamentPublicUrl($media->path, $media));

                            return new HtmlString(
                                '<img src="'.$url.'" alt="" class="max-h-72 rounded-lg border border-gray-200 dark:border-gray-700" />'
                            );
                        })
                        ->visible(fn (Get $get): bool => filled($get('media_id')))
                        ->columnSpanFull(),
                ];
            })
            ->action(function (array $data, BaseFileUpload $component, Get $get, mixed $record) use ($companyResolver): void {
                $company = static::resolveMediaHubCompany($companyResolver, $get, $record);

                if (! $company) {
                    return;
                }

                // Re-checked against the resolved company here (not just used
                // to build the modal's option list) so a tampered client-side
                // media_id can never attach another company's image to this
                // field.
                $media = Media::withoutGlobalScopes()
                    ->where('company_id', $company->getKey())
                    ->find($data['media_id'] ?? null);

                if (! $media) {
                    return;
                }

                if ($component->isMultiple()) {
                    $existing = collect($component->getState() ?? [])->filter()->values()->all();
                    $component->state([...$existing, $media->path]);

                    return;
                }

                $component->state($media->path);
            });
    }

    /**
     * @param  (Closure(Get $get, mixed $record): ?Company)|null  $companyResolver
     */
    protected static function resolveMediaHubCompany(?Closure $companyResolver, Get $get, mixed $record): ?Company
    {
        return $companyResolver
            ? $companyResolver($get, $record)
            : CompanyMedia::resolve($record, $get('company_id'));
    }

    /**
     * @return array<int, string>
     */
    protected static function mediaHubOptionsForCompany(Company $company, string $search): array
    {
        return Media::withoutGlobalScopes()
            ->where('company_id', $company->getKey())
            ->when($search !== '', fn ($query) => $query->where('original_filename', 'like', "%{$search}%"))
            ->latest()
            ->limit(40)
            ->get()
            ->mapWithKeys(fn (Media $media): array => [$media->getKey() => static::mediaHubOptionLabel($media)])
            ->all();
    }

    protected static function mediaHubOptionLabel(?Media $media): ?string
    {
        if (! $media) {
            return null;
        }

        $url = e((string) CompanyMedia::filamentPublicUrl($media->path, $media));
        $name = e($media->original_filename ?? basename($media->path));

        return <<<HTML
            <div class="flex items-center gap-2 py-0.5">
                <img src="{$url}" alt="" class="h-8 w-8 flex-shrink-0 rounded object-cover" />
                <span class="truncate">{$name}</span>
            </div>
            HTML;
    }
}
