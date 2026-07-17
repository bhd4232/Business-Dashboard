<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use App\Services\Crm\LeadConversionService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewLead extends ViewRecord
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('convertToCustomer')
                ->label('Convert to Customer')
                ->icon(Heroicon::OutlinedUserPlus)
                ->color('success')
                ->visible(fn (Lead $record): bool => ! $record->converted_customer_id)
                ->requiresConfirmation()
                ->action(function (Lead $record): void {
                    $customer = app(LeadConversionService::class)->convertToCustomer($record);
                    Notification::make()
                        ->title("Lead converted to customer: {$customer->name}")
                        ->success()
                        ->send();
                    $this->refreshFormData(['converted_customer_id']);
                }),
        ];
    }
}
