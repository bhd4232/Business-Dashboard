<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\WooCommerceOrderSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Pushes an ERP-side status change on a WooCommerce-sourced order back to
 * WooCommerce, so the two systems stay in sync in both directions — see
 * App\Models\Order::booted()'s updated() hook for the trigger and
 * App\Services\WooCommerceOrderSyncService::pushStatusToWooCommerce() for
 * the actual REST call. Queued (not synchronous in the model hook) so a
 * slow/unreachable WooCommerce site never blocks saving the order.
 */
class PushWooCommerceOrderStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $orderId) {}

    public function handle(WooCommerceOrderSyncService $sync): void
    {
        $order = Order::withoutGlobalScopes()->find($this->orderId);

        if (! $order) {
            return;
        }

        $sync->pushStatusToWooCommerce($order);
    }
}
