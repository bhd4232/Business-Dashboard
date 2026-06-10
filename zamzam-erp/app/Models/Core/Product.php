<?php

namespace App\Models\Core;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'sku', 'name', 'name_chinese', 'category_id',
        'unit', 'weight_kg', 'volume_cm3',
        'description', 'image', 'barcode',
        'has_variants', 'min_stock_alert',
        'regular_price', 'selling_price',
        'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'has_variants'    => 'boolean',
            'is_active'       => 'boolean',
            'weight_kg'       => 'decimal:3',
            'volume_cm3'      => 'decimal:3',
            'regular_price'   => 'decimal:2',
            'selling_price'   => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(\App\Models\Inventory\Barcode::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
