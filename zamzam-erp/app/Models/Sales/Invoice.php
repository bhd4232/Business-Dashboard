<?php

namespace App\Models\Sales;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_no', 'sales_order_id', 'customer_id', 'status',
        'subtotal_bdt', 'discount_bdt', 'delivery_charge_bdt',
        'total_bdt', 'paid_bdt', 'due_bdt',
        'issue_date', 'due_date', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_bdt'        => 'decimal:2',
            'discount_bdt'        => 'decimal:2',
            'delivery_charge_bdt' => 'decimal:2',
            'total_bdt'           => 'decimal:2',
            'paid_bdt'            => 'decimal:2',
            'due_bdt'             => 'decimal:2',
            'issue_date'          => 'date',
            'due_date'            => 'date',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────

    public function scopeOverdue($query)
    {
        return $query->where('status', 'issued')
                     ->where('due_date', '<', now());
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    // ─── Invoice Number Generation ────────────────────────────────────────

    public static function generateInvoiceNo(): string
    {
        $year = now()->format('Y');
        $last = static::where('invoice_no', 'like', "INV-{$year}-%")
            ->orderByDesc('id')
            ->value('invoice_no');

        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return sprintf('INV-%s-%04d', $year, $seq);
    }

    // ─── Recalculate Totals ───────────────────────────────────────────────

    public function recalculateTotals(): void
    {
        $subtotal = $this->items()->sum('subtotal_bdt');
        $discount = (float) $this->discount_bdt;
        $delivery = (float) $this->delivery_charge_bdt;
        $total    = max(0, $subtotal - $discount + $delivery);
        $paid     = (float) $this->paid_bdt;
        $due      = max(0, $total - $paid);

        $this->update([
            'subtotal_bdt' => $subtotal,
            'total_bdt'    => $total,
            'due_bdt'      => $due,
        ]);
    }

    // ─── Recalculate Status ───────────────────────────────────────────────

    public function recalculateStatus(): void
    {
        // Don't override terminal states
        if (in_array($this->status, ['cancelled'], true)) {
            return;
        }

        $total = (float) $this->total_bdt;
        $paid  = (float) $this->paid_bdt;
        $due   = (float) $this->due_bdt;

        if ($paid >= $total && $total > 0) {
            $this->update(['status' => 'paid']);
            return;
        }

        if ($paid > 0 && $paid < $total) {
            $this->update(['status' => 'partial']);
            return;
        }

        // Overdue: issued status + past due_date + still has due
        if ($this->status === 'issued' && $this->due_date && $this->due_date->isPast() && $due > 0) {
            $this->update(['status' => 'overdue']);
            return;
        }
    }

    // ─── Sync Payment from Sales Order ───────────────────────────────────

    public function syncFromOrder(SalesOrder $order): void
    {
        $newPaid = (float) $order->paid_bdt;
        $total   = (float) $this->total_bdt;
        $due     = max(0, $total - $newPaid);

        $this->update([
            'paid_bdt' => $newPaid,
            'due_bdt'  => $due,
        ]);

        $this->recalculateStatus();
    }

    // ─── State Helpers ────────────────────────────────────────────────────

    public function canBeEdited(): bool
    {
        return in_array($this->status, ['draft', 'issued'], true);
    }

    public function canBeIssued(): bool
    {
        return $this->status === 'draft';
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['draft', 'issued', 'partial', 'overdue'], true);
    }
}
