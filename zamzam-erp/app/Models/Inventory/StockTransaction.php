<?php

namespace App\Models\Inventory;

use App\Enums\StockTransactionType;
use App\Models\Core\Product;
use App\Models\Core\ProductVariant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockTransaction extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'product_id', 'product_variant_id', 'warehouse_id',
        'type', 'quantity', 'balance_after', 'unit_cost_bdt',
        'reference_type', 'reference_id', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type'          => StockTransactionType::class,
            'quantity'      => 'integer',
            'balance_after' => 'integer',
            'unit_cost_bdt' => 'decimal:4',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo('reference');
    }
}
