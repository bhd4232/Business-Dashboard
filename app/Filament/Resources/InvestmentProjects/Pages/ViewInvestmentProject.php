<?php

namespace App\Filament\Resources\InvestmentProjects\Pages;

use App\Filament\Resources\InvestmentProjects\InvestmentProjectResource;
use App\Filament\Resources\ProjectSettlements\ProjectSettlementResource;
use App\Services\Investment\SettlementService;
use App\Support\MoneyFormatter;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewInvestmentProject extends ViewRecord
{
    protected static string $resource = InvestmentProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('settle')
                ->label('Calculate & Settle')
                ->icon('heroicon-o-calculator')
                ->color('danger')
                ->visible(fn (): bool => $this->record->status === 'closed' && (auth()->user()?->hasPermission('investments.settle') ?? false))
                ->requiresConfirmation()
                ->modalDescription('This creates an immutable settlement and investor payout schedule from the recorded direct costs.')
                ->schema([TextInput::make('total_revenue')->numeric()->prefix('BDT')->minValue(0)->required()])
                ->action(function (array $data): void {
                    $settlement = app(SettlementService::class)->calculateAndSettle($this->record, (float) $data['total_revenue'], (int) auth()->id());
                    Notification::make()->success()->title('Settlement confirmed')->body('Net profit: BDT '.MoneyFormatter::number((float) $settlement->net_profit))->send();
                    $this->redirect(ProjectSettlementResource::getUrl('view', ['record' => $settlement]));
                }),
        ];
    }
}
