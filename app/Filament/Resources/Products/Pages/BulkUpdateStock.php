<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

/**
 * The owner's replacement for a CSV-upload modal as the primary "Bulk
 * Update Stock" flow: every product, searchable/filterable (name, SKU, ID,
 * category — Filament's table search covers all `searchable()` columns),
 * with current stock, how many pieces short of the reorder level, and an
 * inline-editable New Stock field per row.
 *
 * Scoped to `Product` rows only, matching how the main Products list
 * (`ProductsTable`) already only lists products, not variant rows —
 * variant stock stays editable via the product's own edit-page variant
 * repeater, or the CSV import below (which does support variant SKUs).
 *
 * `#[Url] $shortageOnly` lets the Total Shortage stat card deep-link here
 * pre-filtered via a plain query string (`?shortageOnly=1`), the same
 * technique `CourierMerchantDashboard`'s `#[Url] $providerFilter` uses —
 * deliberately NOT Filament's own `?tableFilters=...` mechanism, which
 * that page's own code comment documents as not applying from a cold GET
 * request in this install.
 *
 * New Stock values are staged in Livewire state and remain blank until the
 * user enters them. The header's Save changes action applies every staged
 * value together through `Product::setStockFromProductForm()`, the same
 * ledger-safe path as the product edit form, so every actual stock change
 * still produces a proper `StockMovement`.
 */
class BulkUpdateStock extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = ProductResource::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Bulk Update Stock';

    protected string $view = 'filament.resources.products.pages.bulk-update-stock';

    #[Url]
    public bool $shortageOnly = false;

    /** @var array<int|string, int|string|null> */
    public array $stockUpdates = [];

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->canPerformModelAbility('update', Product::class) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Product::query()
                ->when($this->shortageOnly, fn (Builder $query) => $query->whereColumn('stock', '<=', 'reorder_level')))
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('name')
                    ->label('Product Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('stock')
                    ->label('In Stock')
                    ->badge()
                    ->sortable()
                    ->color(fn (Product $record): string => $record->isLowStock() ? 'danger' : 'success'),

                TextColumn::make('reorder_level')
                    ->label('Reorder Level')
                    ->toggleable(),

                TextColumn::make('short_by')
                    ->label('Short By')
                    ->state(fn (Product $record): int => max((int) $record->reorder_level - (int) $record->stock, 0))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),

                TextInputColumn::make('new_stock')
                    ->label('New Stock')
                    ->type('number')
                    ->rules(['nullable', 'integer', 'min:0'])
                    ->getStateUsing(fn (Product $record): ?int => $this->stockUpdates[$record->getKey()] ?? null)
                    ->updateStateUsing(function (Product $record, mixed $state): ?int {
                        $recordKey = $record->getKey();

                        if ($state === null || $state === '') {
                            unset($this->stockUpdates[$recordKey]);

                            return null;
                        }

                        $this->stockUpdates[$recordKey] = (int) $state;

                        return (int) $state;
                    }),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable(),

                Filter::make('low_stock')
                    ->label('Low stock only')
                    ->query(fn (Builder $query): Builder => $query->whereColumn('stock', '<=', 'reorder_level'))
                    ->toggle(),
            ])
            ->searchPlaceholder('Search name, SKU, ID, category...')
            ->defaultSort('name');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveChanges')
                ->label('Save changes')
                ->icon('heroicon-o-check')
                ->visible(fn (): bool => auth()->user()?->canPerformModelAbility('update', Product::class))
                ->action(function (): void {
                    $this->saveStockChanges();
                }),
        ];
    }

    public function saveStockChanges(): void
    {
        abort_unless(auth()->user()?->canPerformModelAbility('update', Product::class), 403);

        /** @var array<int|string, int|string|null> $validatedUpdates */
        $validatedUpdates = validator(
            ['stockUpdates' => $this->stockUpdates],
            [
                'stockUpdates' => ['array'],
                'stockUpdates.*' => ['nullable', 'integer', 'min:0'],
            ],
        )->validate()['stockUpdates'];

        $updates = collect($validatedUpdates)
            ->reject(fn (mixed $stock): bool => $stock === null || $stock === '')
            ->map(fn (mixed $stock): int => (int) $stock)
            ->all();

        if ($updates === []) {
            Notification::make()
                ->title('No stock changes to save')
                ->body('Enter a New Stock value for at least one product.')
                ->warning()
                ->send();

            return;
        }

        $updatedCount = DB::transaction(function () use ($updates): int {
            $products = Product::query()
                ->whereKey(array_keys($updates))
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Product $product): string => (string) $product->getKey());

            abort_unless($products->count() === count($updates), 403);

            $updatedCount = 0;

            foreach ($updates as $productId => $newStock) {
                /** @var Product $product */
                $product = $products->get((string) $productId);

                if ((int) $product->stock === $newStock) {
                    continue;
                }

                $product->setStockFromProductForm($newStock);
                $updatedCount++;
            }

            return $updatedCount;
        });

        $this->stockUpdates = [];
        $this->resetTable();

        $notification = Notification::make()
            ->title($updatedCount > 0 ? 'Stock changes saved' : 'No stock changes required')
            ->body($updatedCount > 0
                ? "Updated stock for {$updatedCount} products."
                : 'The entered values already match the current stock.');

        if ($updatedCount > 0) {
            $notification->success();
        } else {
            $notification->info();
        }

        $notification->send();
    }
}
