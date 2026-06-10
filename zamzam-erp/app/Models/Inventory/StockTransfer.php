<?php

namespace App\Models\Inventory;

use App\Enums\TransferStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends Model
{
    protected $fillable = [
        'transfer_no', 'from_warehouse_id', 'to_warehouse_id',
        'status', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
        ];
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransferItem::class, 'stock_transfer_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canBeCompleted(): bool
    {
        return $this->status === TransferStatus::InTransit;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [TransferStatus::Pending, TransferStatus::InTransit]);
    }
}
