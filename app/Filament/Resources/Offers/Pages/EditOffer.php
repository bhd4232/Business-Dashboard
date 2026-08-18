<?php

namespace App\Filament\Resources\Offers\Pages;

use App\Filament\Resources\Offers\OfferResource;
use App\Services\OfferLandingPageAiGenerator;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditOffer extends EditRecord
{
    protected static string $resource = OfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateLandingPage')
                ->label('Generate Landing Page with AI')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('gray')
                ->visible(fn (): bool => $this->record->items()->exists())
                ->requiresConfirmation()
                ->modalDescription('This overwrites the current landing page sections below with AI-drafted ones. You can still edit every section afterwards.')
                ->action(function (): void {
                    try {
                        app(OfferLandingPageAiGenerator::class)->generate($this->record);
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Could not generate the landing page')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->record->refresh();
                    $this->fillForm();

                    Notification::make()
                        ->title('Landing page generated')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
