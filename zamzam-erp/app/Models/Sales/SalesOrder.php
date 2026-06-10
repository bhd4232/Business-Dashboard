<?php

namespace App\Models\Sales;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class SalesOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_no', 'customer_id', 'type', 'source', 'status',
        'price_tier_id', 'subtotal_bdt', 'discount_bdt', 'discount_percent',
        'delivery_charge_bdt', 'total_bdt', 'paid_bdt', 'due_bdt',
        'delivery_address', 'delivery_city', 'delivery_area',
        'notes', 'internal_notes',
        'cancel_reason', 'on_hold_reason', 'flag_reason',
        'delivery_partner', 'delivery_partner_id', 'delivery_partner_status', 'delivery_type',
        'confirmed_by', 'confirmed_at', 'shipping_at', 'delivered_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_bdt'        => 'decimal:2',
            'discount_bdt'        => 'decimal:2',
            'discount_percent'    => 'decimal:2',
            'delivery_charge_bdt' => 'decimal:2',
            'total_bdt'           => 'decimal:2',
            'paid_bdt'            => 'decimal:2',
            'due_bdt'             => 'decimal:2',
            'confirmed_at'        => 'datetime',
            'shipping_at'         => 'datetime',
            'delivered_at'        => 'datetime',
        ];
    }

    // ─── Status helpers ───────────────────────────────────────────────────

    public function canBeConfirmed(): bool
    {
        return $this->status === 'draft' && $this->items()->count() > 0;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['draft', 'on_hold', 'confirmed', 'processing']);
    }

    public function canBeEdited(): bool
    {
        return $this->status === 'draft';
    }

    public function canBeDeleted(): bool
    {
        return in_array($this->status, ['draft', 'cancelled']);
    }

    // ─── Totals ───────────────────────────────────────────────────────────

    public function recalculateTotals(): void
    {
        $subtotal = (float) $this->items()->sum('subtotal_bdt');

        // Recalculate discount_bdt from discount_percent if only percent is set
        $discountBdt = (float) $this->discount_bdt;
        if ($this->discount_percent > 0 && $discountBdt === 0.0) {
            $discountBdt = round($subtotal * ($this->discount_percent / 100), 2);
        }

        $total = $subtotal - $discountBdt + (float) $this->delivery_charge_bdt;
        $due   = max(0, $total - (float) $this->paid_bdt);

        $this->update([
            'subtotal_bdt'  => $subtotal,
            'discount_bdt'  => $discountBdt,
            'total_bdt'     => $total,
            'due_bdt'       => $due,
        ]);
    }

    // ─── Relationships ────────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function priceTier(): BelongsTo
    {
        return $this->belongsTo(PriceTier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SoItem::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SoPayment::class)->orderBy('payment_date')->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SoAttachment::class)->orderBy('id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['cancelled', 'returned']);
    }
}
