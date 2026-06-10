<?php

namespace App\Models\Inventory;

use App\Models\Core\Product;
use App\Models\Core\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferItem extends Model
{
    protected $table = 'transfer_items';

    protected $fillable = [
        'stock_transfer_id', 'product_id', 'product_variant_id',
        'quantity', 'received_qty',
    ];

    protected function casts(): array
    {
        return [
            'quantity'     => 'integer',
            'received_qty' => 'integer',
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function getPendingQtyAttribute(): int
    {
        return $this->quantity - $this->received_qty;
    }
}
