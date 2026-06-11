<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Concerns\HasStickyHeaderFormActions;
use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['price'] = $data['sale_price'];
        $this->requestedStock = (int) ($data['stock'] ?? 0);
        unset($data['stock']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->setStockFromProductForm($this->requestedStock);
    }

    public function getLivewireComponentName(): string
    {
        return 'app.filament.resources.products.pages.create-product';
    }
}
