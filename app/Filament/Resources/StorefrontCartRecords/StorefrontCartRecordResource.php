<?php

namespace App\Filament\Resources\StorefrontCartRecords;

use App\Filament\Clusters\Storefront;
use App\Filament\Resources\StorefrontCartRecords\Pages\ListStorefrontCartRecords;
use App\Filament\Resources\StorefrontCartRecords\Pages\ViewStorefrontCartRecord;
use App\Models\Product;
use App\Models\StorefrontCartRecord;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema as DatabaseSchema;

class StorefrontCartRecordResource extends Resource
{
    protected static ?string $model = StorefrontCartRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $cluster = Storefront::class;

    protected static ?string $navigationLabel = 'Incomplete Checkouts';

    protected static ?string $modelLabel = 'Incomplete checkout';

    protected static ?int $navigationSort = 9;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('updated_at')->label('Last activity')->dateTime()->sortable(),
                TextColumn::make('customer_name')->label('Customer')->placeholder('Unknown')->searchable(),
                TextColumn::make('phone')->placeholder('-')->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => StorefrontCartRecord::STATUSES[$state] ?? ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        StorefrontCartRecord::STATUS_CONVERTED => 'success',
                        StorefrontCartRecord::STATUS_REMINDED => 'warning',
                        StorefrontCartRecord::STATUS_CHECKOUT_STARTED => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('items')
                    ->label('Items')
                    ->state(fn (StorefrontCartRecord $record): int => $record->itemCount())
                    ->numeric(),
                TextColumn::make('subtotal')->moneyWithoutTrailingZeroes('BDT')->sortable(),
                TextColumn::make('reminder_count')->label('Reminders')->numeric()->toggleable(),
                TextColumn::make('recoveredOrder.order_number')->label('Order')->placeholder('-')->searchable(),
                TextColumn::make('recovered_at')->label('Recovered')->dateTime()->placeholder('-')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(StorefrontCartRecord::STATUSES),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Checkout contact')
                    ->schema([
                        TextEntry::make('customer_name')->label('Name')->placeholder('-'),
                        TextEntry::make('phone')->placeholder('-'),
                        TextEntry::make('email')->placeholder('-'),
                        TextEntry::make('address')->placeholder('-')->columnSpanFull(),
                    ])
                    ->columns(3),
                Section::make('Cart snapshot')
                    ->schema([
                        TextEntry::make('items')
                            ->label('Items')
                            ->state(fn (StorefrontCartRecord $record): string => static::itemSummary($record))
                            ->columnSpanFull(),
                        TextEntry::make('subtotal')->moneyWithoutTrailingZeroes('BDT'),
                    ])
                    ->columns(2),
                Section::make('Recovery lifecycle')
                    ->schema([
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => StorefrontCartRecord::STATUSES[$state] ?? ucfirst($state)),
                        TextEntry::make('checkout_started_at')->dateTime()->placeholder('-'),
                        TextEntry::make('last_activity_at')->dateTime()->placeholder('-'),
                        TextEntry::make('reminded_at')->dateTime()->placeholder('-'),
                        TextEntry::make('last_reminder_channels')
                            ->formatStateUsing(fn ($state): string => is_array($state) ? implode(', ', $state) : '-')
                            ->placeholder('-'),
                        TextEntry::make('recovery_opened_at')->dateTime()->placeholder('-'),
                        TextEntry::make('converted_at')->dateTime()->placeholder('-'),
                        TextEntry::make('recovered_at')->dateTime()->placeholder('-'),
                        TextEntry::make('recoveredOrder.order_number')->label('Recovered order')->placeholder('-'),
                    ])
                    ->columns(3),
            ]);
    }

    protected static function itemSummary(StorefrontCartRecord $record): string
    {
        $items = collect($record->items);
        $products = Product::withoutGlobalScopes()
            ->where('company_id', $record->company_id)
            ->whereIn('id', $items->pluck('product_id')->filter()->unique()->all())
            ->pluck('name', 'id');

        return $items
            ->map(fn (array $item): string => ($products[(int) ($item['product_id'] ?? 0)] ?? 'Unavailable product').' × '.((int) ($item['quantity'] ?? 0)))
            ->implode(', ');
    }

    public static function canViewAny(): bool
    {
        return DatabaseSchema::hasTable('storefront_cart_records') && (Auth::user()?->hasPermission('sales.view') ?? false);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canView($record): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStorefrontCartRecords::route('/'),
            'view' => ViewStorefrontCartRecord::route('/{record}'),
        ];
    }
}
