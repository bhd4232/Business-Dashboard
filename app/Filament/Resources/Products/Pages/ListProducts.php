<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Services\ProductCsvService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importCsv')
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->visible(fn (): bool => auth()->user()?->canPerformModelAbility('create', Product::class)
                    && auth()->user()?->canPerformModelAbility('update', Product::class))
                ->schema([
                    FileUpload::make('csv')
                        ->label('Product CSV')
                        ->disk('local')
                        ->directory('imports/products')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                        ->required()
                        ->helperText('Use SKU as the unique key. Existing SKUs will be updated.'),
                ])
                ->action(function (array $data, ProductCsvService $products): void {
                    abort_unless(
                        auth()->user()?->canPerformModelAbility('create', Product::class)
                            && auth()->user()?->canPerformModelAbility('update', Product::class),
                        403,
                    );

                    $file = is_array($data['csv'] ?? null)
                        ? reset($data['csv'])
                        : ($data['csv'] ?? null);

                    $path = $file ? Storage::disk('local')->path($file) : null;

                    if (! $path) {
                        return;
                    }

                    $result = $products->import($path);

                    Storage::disk('local')->delete($file);

                    Notification::make()
                        ->title('Products imported')
                        ->body("Created {$result['created']} and updated {$result['updated']} products.")
                        ->success()
                        ->send();
                }),

            Action::make('downloadSampleCsv')
                ->label('Sample CSV')
                ->icon('heroicon-o-document-arrow-down')
                ->url(route('products.import.sample'))
                ->openUrlInNewTab()
                ->visible(fn (): bool => auth()->user()?->canPerformModelAbility('viewAny', Product::class)),

            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(route('products.export.csv'))
                ->openUrlInNewTab()
                ->visible(fn (): bool => auth()->user()?->canPerformModelAbility('viewAny', Product::class)),

            CreateAction::make(),
        ];
    }
}
