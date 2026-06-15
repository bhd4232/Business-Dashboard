<?php

namespace App\Models;

use App\Services\OrderWorkflowService;
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
            app(OrderWorkflowService::class)->syncPreviousCustomerBalance($order);
        });

        static::deleted(function (Order $order): void {
            app(OrderWorkflowService::class)->deleteStockMovements($order);
            app(OrderWorkflowService::class)->syncCustomerBalance($order);
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
        do {
            $number = 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
        } while (self::query()->where('order_number', $number)->exists());

        return $number;
    }

    public function syncTotalsStockAndCustomerBalance(): void
    {
        app(OrderWorkflowService::class)->sync($this);
    }

    public function syncCustomerBalance(): void
    {
        app(OrderWorkflowService::class)->syncCustomerBalance($this);
    }
}
