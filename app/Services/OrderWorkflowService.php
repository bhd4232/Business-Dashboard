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
        // Group per product + variant so variable products deduct stock
        // from the exact variant that was sold.
        $grouped = $items
            ->groupBy(fn ($item): string => $item->product_id.':'.($item->product_variant_id ?? 0))
            ->map(fn ($groupItems) => [
                'product_id' => (int) $groupItems->first()->product_id,
                'product_variant_id' => $groupItems->first()->product_variant_id,
                'quantity' => (int) $groupItems->sum('quantity'),
            ]);

        $keptMovementIds = [];

        foreach ($grouped as $line) {
            $movement = StockMovement::query()->updateOrCreate(
                [
                    'product_id' => $line['product_id'],
                    'product_variant_id' => $line['product_variant_id'],
                    'type' => 'sale',
                    'reference_type' => Order::class,
                    'reference_id' => $order->getKey(),
                ],
                [
                    'company_id' => $order->company_id,
                    'quantity' => $line['quantity'],
                    'note' => "Invoice {$order->order_number}",
                ],
            );

            $keptMovementIds[] = $movement->getKey();
        }

        StockMovement::query()
            ->where('type', 'sale')
            ->where('reference_type', Order::class)
            ->where('reference_id', $order->getKey())
            ->whereNotIn('id', $keptMovementIds)
            ->get()
            ->each
            ->delete();
    }
}
