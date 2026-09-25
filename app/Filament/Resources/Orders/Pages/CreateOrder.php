<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Concerns\HasStickyHeaderFormActions;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateOrder extends CreateRecord
{
    use HasStickyHeaderFormActions;

    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getStickySaveFormAction(),
        ];
    }

    /**
     * The "Payment Method" account picked next to Paid Amount is not an
     * order column; hand it to the order so its first Payments History row
     * posts into that account (see Order::booted()'s `created` hook).
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var Order $record */
        $record = new ($this->getModel())($data);
        $accountId = $this->data['payment_account_id'] ?? null;
        $record->initialPaymentAccountId = filled($accountId) ? (int) $accountId : null;
        $record->save();

        return $record;
    }

    /**
     * After creating an order, go straight back to the orders list (owner
     * request) instead of Filament's default jump to the new order's View
     * page. The "Created" success toast still fires.
     */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
