<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Concerns\HasStickyHeaderFormActions;
use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    use HasStickyHeaderFormActions;

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
        $data['sale_price'] ??= $data['price'] ?? null;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['price'] = $data['sale_price'];
        $this->requestedStock = (int) ($data['stock'] ?? $this->record->stock);
        unset($data['stock']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->setStockFromProductForm($this->requestedStock);
    }
}
