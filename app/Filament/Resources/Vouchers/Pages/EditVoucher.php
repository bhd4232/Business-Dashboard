<?php

namespace App\Filament\Resources\Vouchers\Pages;

use App\Filament\Concerns\HasStickyHeaderFormActions;
use App\Filament\Resources\Vouchers\VoucherResource;
use App\Services\VoucherService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditVoucher extends EditRecord
{
    use HasStickyHeaderFormActions;

    protected static string $resource = VoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), $this->getStickySaveFormAction()];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(VoucherService::class)->update($record, $data);
    }
}
