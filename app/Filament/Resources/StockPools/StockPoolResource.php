<?php

namespace App\Filament\Resources\StockPools;

use App\Filament\Clusters\Inventory;
use App\Filament\Resources\StockPools\Pages\CreateStockPool;
use App\Filament\Resources\StockPools\Pages\EditStockPool;
use App\Filament\Resources\StockPools\Pages\ListStockPools;
use App\Models\Product;
use App\Models\StockPool;
use App\Services\StockMovementService;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Superadmin-only, cross-company by design: links Product rows that belong
 * to different companies but are physically the same stock in one warehouse
 * (e.g. ZamZam International wholesale + ZamZam Gadget retail selling the
 * same units). See App\Models\StockPool for the full mechanics.
 */
class StockPoolResource extends Resource
{
    protected static ?string $model = StockPool::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $cluster = Inventory::class;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Shared Stock Pools';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Shared Stock')
                ->columnSpanFull()
                ->description('Link products across companies that physically share one warehouse\'s stock. Selling in any linked company updates the same live quantity everywhere — no company ever shows a stale or double-counted number.')
                ->schema([
                    Select::make('source_product_id')
                        ->label('Source product (holds the real stock)')
                        ->options(fn (?StockPool $record): array => self::productOptions(currentPoolId: $record?->getKey()))
                        ->searchable()
                        ->required()
                        ->live()
                        ->helperText('The product whose opening/purchase history represents the real physical inventory — usually the main/wholesale company\'s product. Its own stock movement log carries the true history; every other linked product below is a pass-through.'),
                    Select::make('member_product_ids')
                        ->label('Other linked products (pass-through)')
                        ->options(fn (Get $get, ?StockPool $record): array => self::productOptions(
                            excludeId: $get('source_product_id') ? (int) $get('source_product_id') : null,
                            currentPoolId: $record?->getKey(),
                        ))
                        ->multiple()
                        ->searchable()
                        ->required()
                        ->minItems(1)
                        ->helperText('Other companies\' products that sell the exact same physical units. Simple (non-variant) products only.'),
                ])
                ->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sourceProduct.name')
                    ->label('Source product')
                    ->state(fn (StockPool $record): string => $record->sourceProduct
                        ? "{$record->sourceProduct->name} ({$record->sourceProduct->company?->name})"
                        : '—')
                    ->searchable(),
                TextColumn::make('members')
                    ->label('Also linked')
                    ->state(fn (StockPool $record): string => self::memberProducts($record)
                        ->reject(fn (Product $p): bool => (int) $p->getKey() === (int) $record->source_product_id)
                        ->map(fn (Product $p): string => "{$p->name} ({$p->company?->name})")
                        ->implode(', ') ?: '—')
                    ->wrap(),
                TextColumn::make('shared_stock')
                    ->label('Live shared stock')
                    ->state(fn (StockPool $record): int => (int) $record->sourceProduct?->stock)
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(fn (StockPool $record) => self::syncMembers($record, [])),
            ]);
    }

    /** @return Collection<int, Product> */
    public static function memberProducts(StockPool $pool): Collection
    {
        return Product::query()
            ->withoutGlobalScopes()
            ->where('stock_pool_id', $pool->getKey())
            ->with('company')
            ->get();
    }

    /**
     * Sets stock_pool_id on the source + selected member products, clears
     * it from anything previously in this pool that's no longer selected,
     * and resyncs every touched product's displayed stock so removed
     * members immediately revert to their own independent ledger.
     *
     * @param  list<int>  $memberProductIds
     */
    public static function syncMembers(StockPool $pool, array $memberProductIds): void
    {
        $desiredIds = collect($memberProductIds)
            ->push($pool->source_product_id)
            ->filter()
            ->unique()
            ->values();

        $previousIds = Product::query()->withoutGlobalScopes()->where('stock_pool_id', $pool->getKey())->pluck('id');

        if ($desiredIds->isNotEmpty()) {
            Product::query()->withoutGlobalScopes()->whereIn('id', $desiredIds)->update(['stock_pool_id' => $pool->getKey()]);
        }

        $removedIds = $previousIds->diff($desiredIds);

        if ($removedIds->isNotEmpty()) {
            Product::query()->withoutGlobalScopes()->whereIn('id', $removedIds)->update(['stock_pool_id' => null]);
        }

        foreach ($desiredIds->merge($removedIds)->unique() as $id) {
            app(StockMovementService::class)->syncProductStock((int) $id);
        }
    }

    /** @return array<int, string> */
    protected static function productOptions(?int $excludeId = null, ?int $currentPoolId = null): array
    {
        return Product::query()
            ->withoutGlobalScopes()
            ->where('has_variants', false)
            ->where(function ($query) use ($currentPoolId): void {
                $query->whereNull('stock_pool_id');

                if ($currentPoolId) {
                    $query->orWhere('stock_pool_id', $currentPoolId);
                }
            })
            ->when($excludeId, fn ($query) => $query->whereKeyNot($excludeId))
            ->with('company')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Product $product): array => [
                $product->getKey() => "{$product->name} — {$product->company?->name} (SKU: {$product->sku})",
            ])
            ->all();
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->isSuperAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->isSuperAdmin() ?? false;
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->isSuperAdmin() ?? false;
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->isSuperAdmin() ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockPools::route('/'),
            'create' => CreateStockPool::route('/create'),
            'edit' => EditStockPool::route('/{record}/edit'),
        ];
    }
}
