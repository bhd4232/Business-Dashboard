<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Concerns\HasStickyHeaderFormActions;
use App\Filament\Concerns\PersistsProductFormData;
use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    use HasStickyHeaderFormActions;
    use PersistsProductFormData;

    protected static string $resource = ProductResource::class;

    protected int $requestedStock = 0;

    protected function getHeaderActions(): array
    {
        return [
            $this->getStickySaveFormAction(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return self::mutateProductDataBeforeFill($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = self::mutateProductDataBeforeSave($data);
        $this->requestedStock = (int) ($data['stock'] ?? $this->record->stock);
        unset($data['stock']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->setStockFromProductForm($this->requestedStock);
    }
}
