<?php

namespace App\Support;

use Filament\Actions\Action;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Support\Icons\Heroicon;

/**
 * A "Remove" hint action attached to every Filament FileUpload field
 * (wired globally in AppServiceProvider::boot() via FileUpload::configureUsing()).
 *
 * Why this exists: Filament's built-in remove ("×") button lives *inside* the
 * FilePond widget and only appears once FilePond has finished loading a file.
 * When the preview of an already-saved image can't load — a slow or failing
 * company-media URL, an offline mobile webview, the dev server under load — the
 * item stays stuck on "Loading / Waiting for size" with no way to clear it, and
 * a required field then looks permanently occupied so the form can't be saved.
 *
 * This action sits in the field's label row, entirely outside FilePond, so it
 * is always reachable. One click empties the field's Livewire state; FilePond's
 * own `$watch('state')` then picks that up and redraws the field as empty
 * (FileUploadStateCast normalises `null` to an empty state for both single and
 * multiple uploads).
 *
 * The stored file is NOT deleted from storage — same as the native remove
 * button — so a mis-click is recoverable by re-selecting it from the Media Hub
 * or re-uploading.
 */
class FileUploadClearAction
{
    public const NAME = 'clearFileField';

    public static function make(): Action
    {
        return Action::make(self::NAME)
            ->label('Remove')
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->tooltip('Clear this field, even while the preview is still loading')
            ->visible(fn (BaseFileUpload $component): bool => ! $component->isDisabled() && filled($component->getRawState()))
            ->action(function (BaseFileUpload $component): void {
                $component->state(null);
                $component->callAfterStateUpdated();
            });
    }
}
