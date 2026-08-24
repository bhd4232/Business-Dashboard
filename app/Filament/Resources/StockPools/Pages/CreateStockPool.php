<?php

namespace App\Filament\Resources\StockPools\Pages;

use App\Filament\Resources\StockPools\StockPoolResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStockPool extends CreateRecord
{
    protected static string $resource = StockPoolResource::class;

    /** @var list<int> */
    protected array $memberProductIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->memberProductIds = array_map('intval', $data['member_product_ids'] ?? []);
        unset($data['member_product_ids']);

        return $data;
    }

    protected function afterCreate(): void
    {
        StockPoolResource::syncMembers($this->record, $this->memberProductIds);
    }
}
