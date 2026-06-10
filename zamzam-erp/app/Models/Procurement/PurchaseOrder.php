<?php

namespace App\Models\Procurement;

use App\Enums\PoStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'po_number', 'supplier_id', 'currency_id', 'exchange_rate',
        'status', 'order_date', 'expected_delivery_date',
        'subtotal_cny', 'total_cny', 'total_bdt',
        'notes', 'terms_and_conditions',
        'approved_by', 'approved_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status'                  => PoStatus::class,
            'order_date'              => 'date',
            'expected_delivery_date'  => 'date',
            'approved_at'             => 'datetime',
            'exchange_rate'           => 'decimal:6',
            'subtotal_cny'            => 'decimal:2',
            'total_cny'               => 'decimal:2',
            'total_bdt'               => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Core\Currency::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PoItem::class, 'purchase_order_id');
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(ProductPriceHistory::class, 'purchase_order_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [PoStatus::Cancelled->value]);
    }

    public function canBeConfirmed(): bool
    {
        return $this->status === PoStatus::Draft && $this->items()->count() > 0;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [PoStatus::Draft, PoStatus::Confirmed]);
    }

    public function recalculateTotals(): void
    {
        $subtotal = $this->items()->sum('subtotal_cny');
        $this->update([
            'subtotal_cny' => $subtotal,
            'total_cny'    => $subtotal,
            'total_bdt'    => $subtotal * $this->exchange_rate,
        ]);
    }
}
