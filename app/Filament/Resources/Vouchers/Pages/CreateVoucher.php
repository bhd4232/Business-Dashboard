<?php

namespace App\Filament\Resources\Vouchers\Pages;

use App\Filament\Concerns\HasStickyHeaderFormActions;
use App\Filament\Resources\Vouchers\VoucherResource;
use App\Services\VoucherService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateVoucher extends CreateRecord
{
    use HasStickyHeaderFormActions;

    protected static string $resource = VoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->getStickySaveFormAction()];
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(VoucherService::class)->submit($data, Auth::user());
    }
}
