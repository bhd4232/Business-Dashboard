<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\StorefrontMetaAttribution;
use App\Services\StorefrontMetaConversionsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Schema;

class SendStorefrontMetaPurchaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $orderId, public array $context = []) {}

    public function handle(StorefrontMetaConversionsService $conversions): void
    {
        $order = Order::withoutGlobalScopes()
            ->with(['customer', 'items.product', 'company.storefrontSetting'])
            ->find($this->orderId);

        if (! $order || $order->source !== Order::SOURCE_STOREFRONT) {
            return;
        }

        // Tracking is deliberately fail-open: a provider outage must never
        // invalidate or roll back a customer's completed checkout.
        $context = $this->context;

        if (Schema::hasTable('storefront_meta_attributions')) {
            $context = StorefrontMetaAttribution::withoutGlobalScopes()
                ->where('company_id', $order->company_id)
                ->where('order_id', $order->getKey())
                ->first()?->context ?? $context;
        }

        $conversions->sendPurchase($order, is_array($context) ? $context : []);
    }
}
