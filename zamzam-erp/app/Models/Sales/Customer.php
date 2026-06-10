<?php

namespace App\Models\Sales;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'customer_code', 'external_id', 'name', 'business_name', 'type',
        'phone', 'email', 'address', 'city', 'area', 'district',
        'trade_license_no', 'nid_no', 'photo',
        'credit_limit_bdt', 'outstanding_balance_bdt',
        'price_tier_id', 'source', 'source_detail', 'rating',
        'is_active', 'assigned_salesman_id',
        'last_order_at', 'total_orders', 'total_delivered_value_bdt',
        'woo_customer_id', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit_bdt'          => 'decimal:2',
            'outstanding_balance_bdt'   => 'decimal:2',
            'total_delivered_value_bdt' => 'decimal:2',
            'is_active'                 => 'boolean',
            'last_order_at'             => 'datetime',
            'rating'                    => 'integer',
        ];
    }

    // ─── Relationships ─────────────────────────────────────
    public function priceTier(): BelongsTo
    {
        return $this->belongsTo(PriceTier::class);
    }

    public function assignedSalesman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_salesman_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(CustomerTag::class, 'customer_customer_tag')
                    ->withPivot('created_at');
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    // ─── Scopes ────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeWholesale($query)
    {
        return $query->where('type', 'wholesale');
    }

    public function scopeRetail($query)
    {
        return $query->where('type', 'retail');
    }
}
