<?php

namespace App\Observers;

use App\Models\CourierBooking;
use App\Models\Order;
use App\Services\Reseller\ResellerCommissionService;

/**
 * Creates/reverses a reseller commission off the same persisted status +
 * delivery_status pair App\Observers\OrderNotificationObserver already
 * watches -- every path that marks an order delivered (manual staff action,
 * courier webhook, Steadfast sync) converges on App\Services\
 * OrderStatusWorkflowService::transition() setting both fields together,
 * so checking both here is equivalent to checking either one.
 */
class ResellerCommissionObserver
{
    public function __construct(protected ResellerCommissionService $commissions) {}

    public function updated(Order $order): void
    {
        if (! $order->wasChanged(['status', 'delivery_status']) || ! $order->reseller_customer_id) {
            return;
        }

        if ($order->status === Order::STATUS_COMPLETED && $order->delivery_status === CourierBooking::STATUS_DELIVERED) {
            $this->commissions->createForDeliveredOrder($order);

            return;
        }

        if (in_array($order->status, [Order::STATUS_RETURNED, Order::STATUS_REFUNDED], true)) {
            $this->commissions->reverseForOrder($order, "Order marked {$order->status}.");
        }
    }
}
