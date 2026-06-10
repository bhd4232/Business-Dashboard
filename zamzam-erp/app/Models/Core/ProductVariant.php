<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id', 'variant_name', 'sku', 'barcode',
        'attributes', 'weight_kg', 'volume_cm3', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'is_active'  => 'boolean',
            'weight_kg'  => 'decimal:3',
            'volume_cm3' => 'decimal:3',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
