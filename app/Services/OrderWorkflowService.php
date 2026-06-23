<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockMovement;
use Illuminate\Support\Collection;

class OrderWorkflowService
{
    public function sync(Order $order): void
    {
        if (! $order->exists) {
            return;
        }

        $items = $order->items()->get();
        $subtotal = $items->sum(fn (OrderItem $item): float => (int) $item->quantity * (float) $item->unit_price);
        $total = max($subtotal - (float) $order->discount + (float) $order->vat, 0);
        $due = max($total - (float) $order->paid_amount, 0);

        if ($order->subtotal != $subtotal || $order->total_amount != $total || $order->due_amount != $due) {
            $order->forceFill([
                'subtotal' => $subtotal,
                'total_amount' => $total,
                'due_amount' => $due,
            ])->saveQuietly();
        }

        if (in_array($order->status, ['confirmed', 'completed'], true)) {
            $this->syncStockMovements($order, $items);
        } else {
            $this->deleteStockMovements($order);
        }

        $this->syncCustomerBalance($order);
    }

    public function syncPreviousCustomerBalance(Order $order): void
    {
        if ($order->wasChanged('customer_id')) {
            Customer::find($order->getOriginal('customer_id'))?->syncCurrentBalance();
        }
    }

    public function syncCustomerBalance(Order $order): void
    {
        $order->customer?->syncCurrentBalance();
    }

    public function deleteStockMovements(Order $order): void
    {
        StockMovement::query()
            ->where('type', 'sale')
            ->where('reference_type', Order::class)
            ->where('reference_id', $order->getKey())
            ->get()
            ->each
            ->delete();
    }

    protected function syncStockMovements(Order $order, Collection $items): void
    {
        $quantitiesByProduct = $items
            ->groupBy('product_id')
            ->map(fn ($productItems): int => $productItems->sum('quantity'));

        foreach ($quantitiesByProduct as $productId => $quantity) {
            StockMovement::query()->updateOrCreate(
                [
                    'product_id' => $productId,
                    'type' => 'sale',
                    'reference_type' => Order::class,
                    'reference_id' => $order->getKey(),
                ],
                [
                    'company_id' => $order->company_id,
                    'quantity' => $quantity,
                    'note' => "Invoice {$order->order_number}",
                ],
            );
        }

        StockMovement::query()
            ->where('type', 'sale')
            ->where('reference_type', Order::class)
            ->where('reference_id', $order->getKey())
            ->whereNotIn('product_id', $quantitiesByProduct->keys()->all())
            ->get()
            ->each
            ->delete();
    }
}
