<?php

namespace App\Filament\Resources\Resellers\RelationManagers;

use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Which products this reseller picked for their storefront stays read-only
 * here -- curation itself is self-service on the reseller's own account page
 * (App\Http\Controllers\Storefront\ResellerStoreController), per the
 * owner's earlier answer. Wholesale rate is the one exception: it's the
 * company's own cost-basis figure for this reseller+product (used to
 * calculate their delivery commission -- see App\Services\Reseller\
 * ResellerCommissionService), a business-cost decision staff make, not
 * something the reseller sets for themselves.
 */
class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'resellerCatalog';

    protected static ?string $title = 'Store Products';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('sku')->searchable(),
                TextColumn::make('sale_price')->moneyWithoutTrailingZeroes('BDT'),
                TextColumn::make('pivot.wholesale_rate')
                    ->label('Wholesale Rate')
                    ->moneyWithoutTrailingZeroes('BDT')
                    ->placeholder('Not set'),
                IconColumn::make('pivot.is_active')->label('Shown in store')->boolean(),
            ])
            ->recordActions([
                Action::make('setWholesaleRate')
                    ->label('Set Wholesale Rate')
                    ->icon('heroicon-o-currency-bangladeshi')
                    ->visible(fn (): bool => auth()->user()?->canPerformModelAbility('update', Customer::class) ?? false)
                    ->schema([
                        TextInput::make('wholesale_rate')
                            ->label('Wholesale Rate (BDT)')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->helperText('The order\'s actual sale price minus this rate becomes the reseller\'s commission for this product.'),
                    ])
                    ->fillForm(fn ($record): array => ['wholesale_rate' => $record->pivot->wholesale_rate])
                    ->action(function ($record, array $data): void {
                        $this->getOwnerRecord()->resellerCatalog()->updateExistingPivot($record->getKey(), [
                            'wholesale_rate' => $data['wholesale_rate'],
                        ]);
                        Notification::make()->success()->title('Wholesale rate saved')->send();
                    }),
            ])
            ->toolbarActions([]);
    }
}
