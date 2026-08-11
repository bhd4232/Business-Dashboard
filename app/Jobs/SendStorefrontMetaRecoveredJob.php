<?php

namespace App\Jobs;

use App\Models\StorefrontCartRecord;
use App\Services\StorefrontMetaConversionsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendStorefrontMetaRecoveredJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $cartRecordId) {}

    public function handle(StorefrontMetaConversionsService $conversions): void
    {
        $cart = StorefrontCartRecord::withoutGlobalScopes()
            ->with(['recoveredOrder.customer', 'recoveredOrder.items.product', 'recoveredOrder.company.storefrontSetting'])
            ->find($this->cartRecordId);

        if (! $cart?->recoveredOrder) {
            return;
        }

        $conversions->sendRecovered($cart);
    }
}
