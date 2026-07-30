<?php

namespace App\Filament\Resources\Vouchers\Pages;

use App\Filament\Concerns\HasStickyHeaderFormActions;
use App\Filament\Resources\Vouchers\VoucherResource;
use App\Models\Voucher;
use App\Services\FundTransferService;
use App\Services\VoucherService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Throwable;

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

    public function create(bool $another = false): void
    {
        if (($this->form->getRawState()['type'] ?? null) !== Voucher::FORM_TYPE_FUND_TRANSFER) {
            parent::create($another);

            return;
        }

        if ($this->isCreating) {
            return;
        }

        $this->isCreating = true;
        $this->authorizeAccess();

        try {
            $data = $this->form->getState();
            app(FundTransferService::class)->submit($data, Auth::user());
        } catch (Throwable $exception) {
            $this->isCreating = false;

            throw $exception;
        }

        Notification::make()
            ->title('Fund transfer submitted.')
            ->success()
            ->send();

        $this->redirect(VoucherResource::getUrl('index'));
    }
}
