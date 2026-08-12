<?php

namespace App\Jobs;

use App\Models\StorefrontMetaAttribution;
use App\Models\StorefrontMetaEvent;
use App\Services\StorefrontMetaConversionsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RetryStorefrontMetaEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $metaEventId) {}

    public function handle(StorefrontMetaConversionsService $conversions): void
    {
        $event = StorefrontMetaEvent::withoutGlobalScopes()
            ->with([
                'order.customer',
                'order.items.product',
                'order.company.storefrontSetting',
                'orderStatusTransition',
                'cartRecord.recoveredOrder.customer',
                'cartRecord.recoveredOrder.items.product',
                'cartRecord.recoveredOrder.company.storefrontSetting',
            ])
            ->find($this->metaEventId);

        if (! $event || $event->status === StorefrontMetaEvent::STATUS_SENT) {
            return;
        }

        if ($event->event_name === 'Purchase' && $event->order) {
            $context = StorefrontMetaAttribution::withoutGlobalScopes()
                ->where('company_id', $event->company_id)
                ->where('order_id', $event->order_id)
                ->first()?->context;
            $conversions->sendPurchase($event->order, is_array($context) ? $context : []);

            return;
        }

        if ($event->event_name === 'Recovered' && $event->cartRecord) {
            $conversions->sendRecovered($event->cartRecord);

            return;
        }

        if ($event->orderStatusTransition) {
            $conversions->sendOrderStatus($event->orderStatusTransition);
        }
    }
}
