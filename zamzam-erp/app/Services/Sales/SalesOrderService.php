<?php

namespace App\Services\Sales;

use App\Models\Sales\SalesOrder;
use App\Models\Sales\SoItem;
use App\Models\Sales\SoPayment;
use App\Services\Sales\InvoiceService;
use Illuminate\Support\Facades\DB;

class SalesOrderService
{
    // ─── Order Number Generation ──────────────────────────────────────────

    public function generateOrderNo(): string
    {
        $year = now()->format('Y');
        $last = SalesOrder::withTrashed()
            ->where('order_no', 'like', "SO-{$year}-%")
            ->orderByDesc('id')
            ->value('order_no');

        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return sprintf('SO-%s-%04d', $year, $seq);
    }

    // ─── Create ───────────────────────────────────────────────────────────

    public function createSalesOrder(array $data, int $createdBy): SalesOrder
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $order = SalesOrder::create([
                'order_no'            => $this->generateOrderNo(),
                'customer_id'         => $data['customer_id'],
                'type'                => $data['type'] ?? 'wholesale',
                'source'              => $data['source'] ?? 'erp',
                'status'              => 'draft',
                'price_tier_id'       => $data['price_tier_id'] ?? null,
                'discount_bdt'        => $data['discount_bdt'] ?? 0,
                'discount_percent'    => $data['discount_percent'] ?? 0,
                'delivery_charge_bdt' => $data['delivery_charge_bdt'] ?? 0,
                'paid_bdt'            => $data['paid_bdt'] ?? 0,
                'delivery_address'    => $data['delivery_address'] ?? null,
                'delivery_city'       => $data['delivery_city'] ?? null,
                'delivery_area'       => $data['delivery_area'] ?? null,
                'notes'               => $data['notes'] ?? null,
                'internal_notes'      => $data['internal_notes'] ?? null,
                'created_by'          => $createdBy,
            ]);

            foreach ($data['items'] as $item) {
                SoItem::create([
                    'sales_order_id'      => $order->id,
                    'product_id'          => $item['product_id'],
                    'product_variant_id'  => $item['product_variant_id'] ?? null,
                    'quantity'            => $item['quantity'],
                    'unit_price_bdt'      => $item['unit_price_bdt'],
                    'discount_percent'    => $item['discount_percent'] ?? 0,
                    'unit_landing_cost_bdt' => $item['unit_landing_cost_bdt'] ?? 0,
                ]);
            }

            $order->recalculateTotals();

            return $order->fresh(['customer', 'items.product', 'items.variant']);
        });
    }

    // ─── Update ───────────────────────────────────────────────────────────

    public function updateSalesOrder(SalesOrder $order, array $data): SalesOrder
    {
        if (! $order->canBeEdited()) {
            throw new \RuntimeException("Only draft orders can be edited.");
        }

        return DB::transaction(function () use ($order, $data) {
            $order->update([
                'customer_id'         => $data['customer_id'],
                'type'                => $data['type'] ?? $order->type,
                'source'              => $data['source'] ?? $order->source,
                'price_tier_id'       => $data['price_tier_id'] ?? null,
                'discount_bdt'        => $data['discount_bdt'] ?? 0,
                'discount_percent'    => $data['discount_percent'] ?? 0,
                'delivery_charge_bdt' => $data['delivery_charge_bdt'] ?? 0,
                'paid_bdt'            => $data['paid_bdt'] ?? $order->paid_bdt,
                'delivery_address'    => $data['delivery_address'] ?? null,
                'delivery_city'       => $data['delivery_city'] ?? null,
                'delivery_area'       => $data['delivery_area'] ?? null,
                'notes'               => $data['notes'] ?? null,
                'internal_notes'      => $data['internal_notes'] ?? null,
            ]);

            // Full item sync: delete and recreate
            $order->items()->delete();

            foreach ($data['items'] as $item) {
                SoItem::create([
                    'sales_order_id'        => $order->id,
                    'product_id'            => $item['product_id'],
                    'product_variant_id'    => $item['product_variant_id'] ?? null,
                    'quantity'              => $item['quantity'],
                    'unit_price_bdt'        => $item['unit_price_bdt'],
                    'discount_percent'      => $item['discount_percent'] ?? 0,
                    'unit_landing_cost_bdt' => $item['unit_landing_cost_bdt'] ?? 0,
                ]);
            }

            $order->recalculateTotals();

            return $order->fresh(['customer', 'items.product', 'items.variant']);
        });
    }

    // ─── Confirm ──────────────────────────────────────────────────────────

    public function confirmSalesOrder(SalesOrder $order, int $confirmedBy): SalesOrder
    {
        if (! $order->canBeConfirmed()) {
            throw new \RuntimeException("Order cannot be confirmed. It must be in draft status and have at least one item.");
        }

        $order->update([
            'status'       => 'confirmed',
            'confirmed_by' => $confirmedBy,
            'confirmed_at' => now(),
        ]);

        return $order->fresh('customer');
    }

    // ─── Cancel ───────────────────────────────────────────────────────────

    public function cancelSalesOrder(SalesOrder $order): SalesOrder
    {
        if (! $order->canBeCancelled()) {
            throw new \RuntimeException("Order cannot be cancelled at its current status ({$order->status}).");
        }

        $order->update(['status' => 'cancelled']);

        return $order->fresh('customer');
    }

    // ─── Change Status (single) ───────────────────────────────────────────

    public static array $VALID_STATUSES = [
        'draft', 'on_hold', 'confirmed', 'processing',
        'picked', 'dispatched', 'delivered', 'flagged',
        'cancelled', 'returned',
    ];

    public function changeStatus(SalesOrder $order, string $status, ?string $reason, int $updatedBy): SalesOrder
    {
        if (! in_array($status, self::$VALID_STATUSES, true)) {
            throw new \RuntimeException("Invalid status: {$status}");
        }

        $updates = ['status' => $status];

        // Attach reason to the right column
        if ($status === 'cancelled') {
            $updates['cancel_reason']  = $reason;
        } elseif ($status === 'on_hold') {
            $updates['on_hold_reason'] = $reason;
        } elseif ($status === 'flagged') {
            $updates['flag_reason']    = $reason;
        }

        // Timestamps
        if ($status === 'confirmed' && ! $order->confirmed_at) {
            $updates['confirmed_by'] = $updatedBy;
            $updates['confirmed_at'] = now();
        } elseif (in_array($status, ['picked', 'dispatched'], true) && ! $order->shipping_at) {
            $updates['shipping_at']  = now();
        } elseif ($status === 'delivered' && ! $order->delivered_at) {
            $updates['shipping_at']  = $order->shipping_at ?? now();
            $updates['delivered_at'] = now();
        }

        $order->update($updates);

        return $order->fresh('customer');
    }

    // ─── Bulk Change Status ───────────────────────────────────────────────

    public function bulkChangeStatus(array $ids, string $status, ?string $reason, int $updatedBy): array
    {
        $orders  = SalesOrder::whereIn('id', $ids)->get();
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        foreach ($orders as $order) {
            try {
                $this->changeStatus($order, $status, $reason, $updatedBy);
                $results['success']++;
            } catch (\RuntimeException $e) {
                $results['failed']++;
                $results['errors'][] = "Order {$order->order_no}: " . $e->getMessage();
            }
        }

        return $results;
    }

    // ─── Receive Payment ──────────────────────────────────────────────────

    public function receivePayment(SalesOrder $order, array $data, int $receivedBy): SoPayment
    {
        if (in_array($order->status, ['cancelled', 'returned'])) {
            throw new \RuntimeException("Cannot record payment for a {$order->status} order.");
        }

        return DB::transaction(function () use ($order, $data, $receivedBy) {
            $payment = SoPayment::create([
                'sales_order_id' => $order->id,
                'amount_bdt'     => $data['amount_bdt'],
                'method'         => $data['method'] ?? 'cash',
                'payment_type'   => $data['payment_type'] ?? 'payment',
                'reference'      => $data['reference'] ?? null,
                'payment_date'   => $data['payment_date'] ?? now()->toDateString(),
                'notes'          => $data['notes'] ?? null,
                'received_by'    => $receivedBy,
            ]);

            // Add to existing paid_bdt (preserves amounts set before payments table existed)
            $newPaid = (float) $order->paid_bdt + (float) $payment->amount_bdt;
            $due     = max(0, (float) $order->total_bdt - $newPaid);

            $order->update([
                'paid_bdt' => $newPaid,
                'due_bdt'  => $due,
            ]);

            // Sync linked invoice payment if exists
            if ($order->invoice) {
                app(InvoiceService::class)->syncPaymentFromOrder($order->invoice);
            }

            return $payment->load('receivedBy:id,name');
        });
    }

    // ─── Update Payment ───────────────────────────────────────────────────

    public function updatePayment(SalesOrder $order, SoPayment $payment, array $data): SoPayment
    {
        if ($payment->sales_order_id !== $order->id) {
            throw new \RuntimeException("Payment does not belong to this order.");
        }

        return DB::transaction(function () use ($order, $payment, $data) {
            $oldAmount = (float) $payment->amount_bdt;
            $newAmount = (float) $data['amount_bdt'];

            $payment->update([
                'amount_bdt'   => $newAmount,
                'method'       => $data['method'] ?? $payment->method,
                'payment_type' => $data['payment_type'] ?? $payment->payment_type,
                'reference'    => $data['reference'] ?? null,
                'payment_date' => $data['payment_date'] ?? $payment->payment_date,
                'notes'        => $data['notes'] ?? null,
            ]);

            // Adjust paid_bdt by the difference
            $newPaid = max(0, (float) $order->paid_bdt - $oldAmount + $newAmount);
            $due     = max(0, (float) $order->total_bdt - $newPaid);

            $order->update([
                'paid_bdt' => $newPaid,
                'due_bdt'  => $due,
            ]);

            return $payment->fresh('receivedBy:id,name');
        });
    }
}
