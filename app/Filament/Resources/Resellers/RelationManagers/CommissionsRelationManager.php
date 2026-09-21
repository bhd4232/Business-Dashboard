<?php

namespace App\Filament\Resources\Resellers\RelationManagers;

use App\Models\Customer;
use App\Services\Reseller\ResellerCommissionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CommissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'resellerCommissions';

    protected static ?string $title = 'Commissions';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order.order_number')->label('Order'),
                TextColumn::make('gross_margin_amount')->moneyWithoutTrailingZeroes('BDT'),
                TextColumn::make('other_costs_amount')->label('Other Costs')->moneyWithoutTrailingZeroes('BDT'),
                TextColumn::make('commission_amount')->moneyWithoutTrailingZeroes('BDT')->weight('bold'),
                TextColumn::make('status')->badge(),
                TextColumn::make('order_delivered_at')->date(),
                TextColumn::make('holding_until')->date()->label('Payable From'),
            ])
            ->recordActions([
                Action::make('editCosts')
                    ->label('Adjust Costs')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn ($record): bool => in_array($record->status, ['holding', 'payable'], true)
                        && (auth()->user()?->canPerformModelAbility('update', Customer::class) ?? false))
                    ->schema([
                        Repeater::make('cost_breakdown')
                            ->label('Deductions (packaging, return handling, courier, etc.)')
                            ->schema([
                                TextInput::make('label')->required(),
                                TextInput::make('amount')->numeric()->minValue(0)->required()->prefix('৳'),
                            ])
                            ->columns(2)
                            ->addActionLabel('Add cost')
                            ->default([]),
                    ])
                    ->fillForm(fn ($record): array => ['cost_breakdown' => $record->cost_breakdown ?? []])
                    ->action(function ($record, array $data): void {
                        app(ResellerCommissionService::class)->updateCostBreakdown($record, $data['cost_breakdown'] ?? []);
                        Notification::make()->success()->title('Commission costs updated')->send();
                    }),
            ]);
    }
}
