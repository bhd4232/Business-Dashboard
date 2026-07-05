<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Services\CustomerRiskService;
use App\Services\OrderWorkflowService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Order extends Model
{
    use BelongsToCompany;

    public const STATUSES = [
        'draft' => 'Draft',
        'confirmed' => 'Confirmed',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    public const DELIVERY_STATUSES = [
        CourierBooking::STATUS_NOT_BOOKED => 'Not Booked',
        CourierBooking::STATUS_BOOKING_PENDING => 'Booking Pending',
        CourierBooking::STATUS_BOOKED => 'Booked',
        CourierBooking::STATUS_PICKED_UP => 'Picked Up',
        CourierBooking::STATUS_IN_TRANSIT => 'In Transit',
        CourierBooking::STATUS_DELIVERED => 'Delivered',
        CourierBooking::STATUS_PARTIAL_DELIVERED => 'Partial Delivered',
        CourierBooking::STATUS_RETURNED => 'Returned',
        CourierBooking::STATUS_CANCELLED => 'Cancelled',
        CourierBooking::STATUS_FAILED => 'Failed',
    ];

    public const SOURCE_ADMIN = 'admin';

    public const SOURCE_STOREFRONT = 'storefront';

    public const SOURCES = [
        self::SOURCE_ADMIN => 'Admin',
        self::SOURCE_STOREFRONT => 'Storefront',
    ];

    protected $fillable = [
        'company_id',
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
        'delivery_status',
        'source',
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
            $order->order_number ??= static::nextOrderNumber($order->company);
            $order->order_date ??= now()->toDateString();
            $order->status ??= 'draft';
            $order->delivery_status ??= CourierBooking::STATUS_NOT_BOOKED;
            $order->source ??= self::SOURCE_ADMIN;
            $order->customer_name = $order->customer?->name ?? $order->customer_name;
        });

        static::saving(function (Order $order): void {
            $order->customer_name = $order->customer?->name ?? $order->customer_name;
        });

        static::saved(function (Order $order): void {
            $order->syncTotalsStockAndCustomerBalance();
            app(OrderWorkflowService::class)->syncPreviousCustomerBalance($order);
            if ($order->wasChanged('status') && in_array($order->status, ['confirmed', 'completed'], true) && Schema::hasTable('fraud_checks')) {
                app(CustomerRiskService::class)->evaluateOrder($order);
            }
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

    public function storefrontPayments(): HasMany
    {
        return $this->hasMany(StorefrontPayment::class);
    }

    public function courierBookings(): HasMany
    {
        return $this->hasMany(CourierBooking::class);
    }

    public function latestCourierBooking()
    {
        return $this->hasOne(CourierBooking::class)->latestOfMany();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function fraudChecks(): HasMany
    {
        return $this->hasMany(FraudCheck::class);
    }

    public function riskReviews(): HasMany
    {
        return $this->hasMany(CustomerRiskReview::class);
    }

    public function latestRiskReview()
    {
        return $this->hasOne(CustomerRiskReview::class)->latestOfMany();
    }

    public function latestFraudCheck()
    {
        return $this->hasOne(FraudCheck::class)->latestOfMany();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public static function nextOrderNumber(?Company $company = null): string
    {
        $company ??= app()->bound('company.context') ? app('company.context')->company() : null;
        $company ??= Company::defaultCompany();
        $prefix = $company?->invoice_prefix ?: 'INV';
        $base = $prefix.'-'.now()->format('Ymd').'-';
        $lastNumber = self::query()
            ->when($company, fn ($query) => $query->where('company_id', $company->getKey()))
            ->where('order_number', 'like', $base.'%')
            ->orderByDesc('order_number')
            ->value('order_number');

        $sequence = $lastNumber ? ((int) substr($lastNumber, -4)) + 1 : 1;

        return $base.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
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
