<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'confirmed' => 'Confirmed',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'order_number',
        'customer_id',
        'customer_name',
        'order_date',
        'product_id',
        'unit_price',
        'quantity',
        'subtotal',
        'discount',
        'vat',
        'total_amount',
        'paid_amount',
        'due_amount',
        'status',
        'note',
    ];

    protected $casts = [
        'order_date' => 'date',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'vat' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            $order->order_number ??= static::nextOrderNumber();
            $order->order_date ??= now()->toDateString();
            $order->status ??= 'draft';
            $order->customer_name = $order->customer?->name ?? $order->customer_name;
        });

        static::saving(function (Order $order): void {
            $order->customer_name = $order->customer?->name ?? $order->customer_name;
        });

        static::saved(function (Order $order): void {
            $order->syncTotalsStockAndCustomerBalance();

            if ($order->wasChanged('customer_id')) {
                Customer::find($order->getOriginal('customer_id'))?->syncCurrentBalance();
            }
        });

        static::deleted(function (Order $order): void {
            $order->deleteStockMovements();
            $order->syncCustomerBalance();
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public static function nextOrderNumber(): string
    {
        return 'INV-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5));
    }

    public function syncTotalsStockAndCustomerBalance(): void
    {
        if (! $this->exists) {
            return;
        }

        $items = $this->items()->get();
        $subtotal = $items->sum(fn (OrderItem $item): float => (int) $item->quantity * (float) $item->unit_price);
        $total = max($subtotal - (float) $this->discount + (float) $this->vat, 0);
        $due = max($total - (float) $this->paid_amount, 0);

        if ($this->subtotal != $subtotal || $this->total_amount != $total || $this->due_amount != $due) {
            $this->forceFill([
                'subtotal' => $subtotal,
                'total_amount' => $total,
                'due_amount' => $due,
            ])->saveQuietly();
        }

        if (in_array($this->status, ['confirmed', 'completed'], true)) {
            $this->syncStockMovements($items);
        } else {
            $this->deleteStockMovements();
        }

        $this->syncCustomerBalance();
    }

    protected function syncStockMovements($items): void
    {
        $quantitiesByProduct = $items
            ->groupBy('product_id')
            ->map(fn ($productItems): int => $productItems->sum('quantity'));

        foreach ($quantitiesByProduct as $productId => $quantity) {
            StockMovement::query()->updateOrCreate(
                [
                    'product_id' => $productId,
                    'type' => 'sale',
                    'reference_type' => self::class,
                    'reference_id' => $this->getKey(),
                ],
                [
                    'quantity' => $quantity,
                    'note' => "Invoice {$this->order_number}",
                ],
            );
        }

        StockMovement::query()
            ->where('type', 'sale')
            ->where('reference_type', self::class)
            ->where('reference_id', $this->getKey())
            ->whereNotIn('product_id', $quantitiesByProduct->keys()->all())
            ->get()
            ->each
            ->delete();
    }

    protected function deleteStockMovements(): void
    {
        StockMovement::query()
            ->where('type', 'sale')
            ->where('reference_type', self::class)
            ->where('reference_id', $this->getKey())
            ->get()
            ->each
            ->delete();
    }

    public function syncCustomerBalance(): void
    {
        $customer = $this->customer;

        if (! $customer) {
            return;
        }

        $customer->syncCurrentBalance();
    }
}
