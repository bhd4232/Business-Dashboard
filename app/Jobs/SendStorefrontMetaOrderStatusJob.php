<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderStatusTransition;
use App\Services\StorefrontMetaConversionsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendStorefrontMetaOrderStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $transitionId) {}

    public function handle(StorefrontMetaConversionsService $conversions): void
    {
        $transition = OrderStatusTransition::withoutGlobalScopes()
            ->with(['order.customer', 'order.items.product', 'order.company.storefrontSetting'])
            ->find($this->transitionId);

        if (! $transition || $transition->order?->source !== Order::SOURCE_STOREFRONT) {
            return;
        }

        // Status delivery is fail-open: Meta must never invalidate or roll
        // back the underlying ERP/courier transition.
        $conversions->sendOrderStatus($transition);
    }
}
