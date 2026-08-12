<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\GeneratesSequentialNumber;
use App\Services\CustomerRiskService;
use App\Services\OrderWorkflowService;
use App\Services\ShippingFeeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class Order extends Model
{
    use BelongsToCompany, GeneratesSequentialNumber;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_CONFIRMED => 'Confirmed',
        self::STATUS_PROCESSING => 'Processing',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_RETURNED => 'Returned',
        self::STATUS_REFUNDED => 'Refunded',
    ];

    /** Statuses that reserve stock and count towards sales/customer dues. */
    public const ACCOUNTED_STATUSES = [
        self::STATUS_CONFIRMED,
        self::STATUS_PROCESSING,
        self::STATUS_COMPLETED,
    ];

    public const STAGE_CONFIRMED = 'confirmed';

    public const STAGE_PROCESSING = 'processing';

    public const STAGE_SHIPPING = 'shipping';

    public const STAGE_DELIVERED = 'delivered';

    public const STAGE_COMPLETED = 'completed';

    public const STAGE_CANCELLED = 'cancelled';

    public const STAGE_RETURNED = 'returned';

    public const STAGE_REFUNDED = 'refunded';

    public const WORKFLOW_STAGES = [
        self::STAGE_CONFIRMED => 'Confirmed',
        self::STAGE_PROCESSING => 'Processing',
        self::STAGE_SHIPPING => 'Shipping',
        self::STAGE_DELIVERED => 'Delivered',
        self::STAGE_COMPLETED => 'Completed',
        self::STAGE_CANCELLED => 'Cancelled',
        self::STAGE_RETURNED => 'Returned',
        self::STAGE_REFUNDED => 'Refunded',
    ];

    public const REASON_REQUIRED_STAGES = [
        self::STAGE_CANCELLED,
        self::STAGE_RETURNED,
        self::STAGE_REFUNDED,
    ];

    public const WORKFLOW_TRANSITIONS = [
        self::STATUS_DRAFT => [self::STAGE_CONFIRMED, self::STAGE_CANCELLED],
        self::STATUS_CONFIRMED => [self::STAGE_PROCESSING, self::STAGE_SHIPPING, self::STAGE_DELIVERED, self::STAGE_COMPLETED, self::STAGE_CANCELLED, self::STAGE_RETURNED],
        self::STATUS_PROCESSING => [self::STAGE_SHIPPING, self::STAGE_DELIVERED, self::STAGE_COMPLETED, self::STAGE_CANCELLED, self::STAGE_RETURNED],
        self::STAGE_SHIPPING => [self::STAGE_DELIVERED, self::STAGE_RETURNED, self::STAGE_CANCELLED],
        self::STATUS_COMPLETED => [self::STAGE_RETURNED, self::STAGE_REFUNDED],
        self::STATUS_RETURNED => [self::STAGE_REFUNDED],
        self::STATUS_CANCELLED => [],
        self::STATUS_REFUNDED => [],
    ];

    public ?string $statusTransitionStage = null;

    public ?string $statusTransitionReason = null;

    public string $statusTransitionSource = 'direct';

    public ?int $statusTransitionActorId = null;

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

    public const SOURCE_CRM = 'crm';

    public const SOURCE_CHAT = 'chat';

    public const SOURCES = [
        self::SOURCE_ADMIN => 'Admin',
        self::SOURCE_STOREFRONT => 'Storefront',
        self::SOURCE_CRM => 'CRM',
        self::SOURCE_CHAT => 'Chat',
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
        'shipping_zone',
        'shipping_fee',
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
        'shipping_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            $order->order_number ??= static::nextOrderNumber($order->company);
            $order->order_date ??= now()->toDateString();
            $order->status ??= self::STATUS_DRAFT;
            $order->delivery_status ??= CourierBooking::STATUS_NOT_BOOKED;
            $order->source ??= self::SOURCE_ADMIN;
            $order->customer_name = $order->customer?->name ?? $order->customer_name;

            if ($order->shipping_zone === null && $order->company) {
                $fee = app(ShippingFeeService::class)->feeFor($order->customer?->address, $order->company);
                $order->shipping_zone = $fee['zone'];
                $order->shipping_fee = $fee['fee'];
            }
        });

        static::saving(function (Order $order): void {
            $order->customer_name = $order->customer?->name ?? $order->customer_name;
        });

        static::updated(function (Order $order): void {
            if (
                ! $order->wasChanged(['status', 'delivery_status'])
                || ! Schema::hasTable('order_status_transitions')
            ) {
                return;
            }

            $order->statusTransitions()->create([
                'company_id' => $order->company_id,
                'changed_by' => $order->statusTransitionActorId ?? Auth::id(),
                'stage' => $order->statusTransitionStage,
                'from_status' => $order->getRawOriginal('status'),
                'to_status' => $order->status,
                'from_delivery_status' => $order->getRawOriginal('delivery_status'),
                'to_delivery_status' => $order->delivery_status,
                'source' => $order->statusTransitionSource,
                'reason' => $order->statusTransitionReason,
            ]);
        });

        static::saved(function (Order $order): void {
            $order->syncTotalsStockAndCustomerBalance();
            app(OrderWorkflowService::class)->syncPreviousCustomerBalance($order);
            if ($order->wasChanged('status') && $order->isAccounted() && Schema::hasTable('fraud_checks')) {
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

    public function vouchers(): BelongsToMany
    {
        return $this->belongsToMany(Voucher::class)->withTimestamps();
    }

    public function fraudChecks(): HasMany
    {
        return $this->hasMany(FraudCheck::class);
    }

    public function riskReviews(): HasMany
    {
        return $this->hasMany(CustomerRiskReview::class);
    }

    public function statusTransitions(): HasMany
    {
        return $this->hasMany(OrderStatusTransition::class);
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

    protected function sequentialNumberColumn(): string
    {
        return 'order_number';
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

    public function isAccounted(): bool
    {
        return in_array($this->status, self::ACCOUNTED_STATUSES, true);
    }

    public function workflowStage(): string
    {
        if (in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_RETURNED, self::STATUS_REFUNDED], true)) {
            return $this->status;
        }

        if (in_array($this->delivery_status, [
            CourierBooking::STATUS_PICKED_UP,
            CourierBooking::STATUS_IN_TRANSIT,
            CourierBooking::STATUS_PARTIAL_DELIVERED,
        ], true)) {
            return self::STAGE_SHIPPING;
        }

        return $this->status;
    }

    /** @return list<string> */
    public function allowedWorkflowStages(): array
    {
        return self::WORKFLOW_TRANSITIONS[$this->workflowStage()] ?? [];
    }

    public function allowedWorkflowStageOptions(): array
    {
        return collect($this->allowedWorkflowStages())
            ->mapWithKeys(fn (string $stage): array => [$stage => self::WORKFLOW_STAGES[$stage]])
            ->all();
    }

    public function clearStatusTransitionContext(): void
    {
        $this->statusTransitionStage = null;
        $this->statusTransitionReason = null;
        $this->statusTransitionSource = 'direct';
        $this->statusTransitionActorId = null;
    }
}
