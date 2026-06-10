<?php

namespace App\Models\Shipping;

use App\Enums\CostAllocationMethod;
use App\Enums\ShipmentStatus;
use App\Enums\ShippingType;
use App\Models\Procurement\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    protected $fillable = [
        'shipment_no', 'purchase_order_id', 'carrier',
        'container_no', 'container_type', 'bl_number',
        'shipping_type', 'port_loading', 'port_discharge',
        'etd', 'eta', 'atd', 'ata',
        'status', 'cost_allocation_method',
        'customs_agent', 'customs_declaration_no',
        'tracking_url', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'shipping_type'           => ShippingType::class,
            'status'                  => ShipmentStatus::class,
            'cost_allocation_method'  => CostAllocationMethod::class,
            'etd'  => 'date',
            'eta'  => 'date',
            'atd'  => 'date',
            'ata'  => 'date',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(ShipmentCost::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ShipmentDocument::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ShipmentStatusHistory::class)->orderBy('changed_at');
    }

    public function landingCostAllocations(): HasMany
    {
        return $this->hasMany(LandingCostAllocation::class);
    }

    // ── Helpers ────────────────────────────────────────────────────

    public function canAdvanceStatus(): bool
    {
        return $this->status->nextStatus() !== null;
    }

    public function totalCostBdt(): float
    {
        return (float) $this->costs()->sum('amount_bdt');
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [ShipmentStatus::DeliveredToWarehouse->value]);
    }
}
