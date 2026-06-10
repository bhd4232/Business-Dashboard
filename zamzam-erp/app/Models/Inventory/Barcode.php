<?php

namespace App\Models\Inventory;

use App\Enums\BarcodeType;
use App\Models\Core\Product;
use App\Models\Core\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Barcode extends Model
{
    protected $fillable = [
        'product_id', 'product_variant_id',
        'barcode', 'type', 'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'type'       => BarcodeType::class,
            'is_primary' => 'boolean',
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
}
