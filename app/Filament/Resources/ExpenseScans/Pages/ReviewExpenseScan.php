<?php

namespace App\Filament\Resources\ExpenseScans\Pages;

use App\Filament\Concerns\HasStickyHeaderFormActions;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\ExpenseScans\ExpenseScanResource;
use App\Jobs\ProcessExpenseScanJob;
use App\Models\ExpenseScan;
use App\Services\ExpenseScan\ExpenseScanPublisher;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Review page: the photo beside the AI's draft lines. Save keeps the
 * corrections as a draft; Publish saves then turns every line into a real
 * Expense (all-or-nothing, via ExpenseScanPublisher).
 *
 * @property ExpenseScan $record
 */
class ReviewExpenseScan extends EditRecord
{
    use HasStickyHeaderFormActions;

    protected static string $resource = ExpenseScanResource::class;

    protected static ?string $title = 'Review scanned expenses';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->label('Publish')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Publish these expenses?')
                ->modalDescription('Every line becomes a real expense and is posted to its pay-from account. New categories suggested by the AI are created now.')
                ->disabled(fn (): bool => $this->record->isBusy())
                ->action(fn () => $this->publish()),
            $this->getStickySaveFormAction()->label('Save draft'),
            Action::make('reread')
                ->label('Read again with AI')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('The AI reads the photo again and replaces all lines below, including your corrections.')
                ->disabled(fn (): bool => $this->record->isBusy())
                ->action(function (): void {
                    $this->record->forceFill(['status' => ExpenseScan::STATUS_PENDING, 'error_message' => null])->save();
                    ProcessExpenseScanJob::dispatch($this->record->getKey());
                    $this->refreshScan();
                }),
            DeleteAction::make(),
        ];
    }

    public function publish(): void
    {
        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);

        try {
            $count = app(ExpenseScanPublisher::class)->publish($this->record, Auth::user());
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Could not publish yet')
                ->body(collect($exception->errors())->flatten()->implode("\n"))
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title($count === 1 ? '1 expense published' : "{$count} expenses published")
            ->success()
            ->send();

        $this->redirect(ExpenseResource::getUrl('index'));
    }

    /**
     * Polled by the photo panel only while the AI is still reading; once the
     * read has finished, reload so the lines repeater picks up the new rows.
     */
    public function refreshScan(): void
    {
        $this->record->refresh();

        if (! $this->record->isBusy()) {
            $this->redirect(static::getUrl(['record' => $this->record]));
        }
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Draft saved';
    }
}
