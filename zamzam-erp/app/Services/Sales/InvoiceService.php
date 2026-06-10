<?php

namespace App\Services\Sales;

use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Models\Sales\SalesOrder;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    // ─── Invoice Number Generation ────────────────────────────────────────

    public function generateInvoiceNo(): string
    {
        return Invoice::generateInvoiceNo();
    }

    // ─── Create Invoice (standalone or SO-linked) ─────────────────────────

    public function createInvoice(array $data, int $createdBy): Invoice
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $invoice = Invoice::create([
                'invoice_no'          => $this->generateInvoiceNo(),
                'sales_order_id'      => $data['sales_order_id'] ?? null,
                'customer_id'         => $data['customer_id'],
                'status'              => 'draft',
                'discount_bdt'        => $data['discount_bdt'] ?? 0,
                'delivery_charge_bdt' => $data['delivery_charge_bdt'] ?? 0,
                'subtotal_bdt'        => 0,
                'total_bdt'           => 0,
                'paid_bdt'            => 0,
                'due_bdt'             => 0,
                'issue_date'          => $data['issue_date'],
                'due_date'            => $data['due_date'] ?? null,
                'notes'               => $data['notes'] ?? null,
                'created_by'          => $createdBy,
            ]);

                foreach ($data['items'] as $item) {
                    InvoiceItem::create([
                        'invoice_id'         => $invoice->id,
                        'product_id'         => $item['product_id'],
                        'product_variant_id' => $item['product_variant_id'] ?? null,
                        'quantity'           => $item['quantity'],
                        'unit_price_bdt'     => $item['unit_price_bdt'],
                        'discount_percent'   => $item['discount_percent'] ?? 0,
                        'subtotal_bdt'       => 0,
                    ]);
                }

            $invoice->recalculateTotals();
            $invoice->recalculateStatus();

            return $invoice->fresh(['customer', 'items.product', 'items.variant', 'salesOrder']);
        });
    }

    // ─── Create from Sales Order ──────────────────────────────────────────

    public function createFromSalesOrder(SalesOrder $order, array $extra, int $createdBy): Invoice
    {
        // Copy items from SO
        $items = $order->items->map(fn ($soItem) => [
            'product_id'         => $soItem->product_id,
            'product_variant_id' => $soItem->product_variant_id ?? null,
            'quantity'           => $soItem->quantity,
            'unit_price_bdt'     => $soItem->unit_price_bdt,
            'discount_percent'   => $soItem->discount_percent ?? 0,
        ])->toArray();

        $data = array_merge([
            'sales_order_id'      => $order->id,
            'customer_id'         => $order->customer_id,
            'discount_bdt'        => $order->discount_bdt ?? 0,
            'delivery_charge_bdt' => $order->delivery_charge_bdt ?? 0,
            'issue_date'          => now()->toDateString(),
            'items'               => $items,
        ], $extra);

        return $this->createInvoice($data, $createdBy);
    }

    // ─── Update Invoice ───────────────────────────────────────────────────

    public function updateInvoice(Invoice $invoice, array $data): Invoice
    {
        if (! $invoice->canBeEdited()) {
            throw new \RuntimeException("Only draft or issued invoices can be edited.");
        }

        return DB::transaction(function () use ($invoice, $data) {
            $invoice->update([
                'customer_id'         => $data['customer_id']         ?? $invoice->customer_id,
                'issue_date'          => $data['issue_date']          ?? $invoice->issue_date,
                'due_date'            => $data['due_date']             ?? null,
                'notes'               => $data['notes']               ?? null,
                'discount_bdt'        => $data['discount_bdt']        ?? 0,
                'delivery_charge_bdt' => $data['delivery_charge_bdt'] ?? 0,
            ]);

            if (isset($data['items'])) {
                // Full item sync: delete + recreate
                $invoice->items()->delete();

                foreach ($data['items'] as $item) {
                    InvoiceItem::create([
                        'invoice_id'         => $invoice->id,
                        'product_id'         => $item['product_id'],
                        'product_variant_id' => $item['product_variant_id'] ?? null,
                        'quantity'           => $item['quantity'],
                        'unit_price_bdt'     => $item['unit_price_bdt'],
                        'discount_percent'   => $item['discount_percent'] ?? 0,
                        'subtotal_bdt'       => 0,
                    ]);
                }
            }

            $invoice->recalculateTotals();
            $invoice->recalculateStatus();

            return $invoice->fresh(['customer', 'items.product', 'items.variant', 'salesOrder']);
        });
    }

    // ─── Issue Invoice ────────────────────────────────────────────────────

    public function issueInvoice(Invoice $invoice): Invoice
    {
        if (! $invoice->canBeIssued()) {
            throw new \RuntimeException("Only draft invoices can be issued.");
        }

        $invoice->update(['status' => 'issued']);

        return $invoice->fresh('customer');
    }

    // ─── Cancel Invoice ───────────────────────────────────────────────────

    public function cancelInvoice(Invoice $invoice): Invoice
    {
        if (! $invoice->canBeCancelled()) {
            throw new \RuntimeException("Invoice cannot be cancelled at its current status ({$invoice->status}).");
        }

        $invoice->update(['status' => 'cancelled']);

        return $invoice->fresh('customer');
    }

    // ─── Sync Payment from Sales Order ───────────────────────────────────

    public function syncPaymentFromOrder(Invoice $invoice): Invoice
    {
        if (! $invoice->sales_order_id) {
            throw new \RuntimeException("This invoice is not linked to a Sales Order.");
        }

        $order = $invoice->salesOrder;
        $invoice->syncFromOrder($order);

        return $invoice->fresh('customer');
    }
}
