<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerTag extends Model
{
    protected $fillable = [
        'name', 'slug', 'color', 'description',
        'is_auto_assign', 'auto_assign_condition',
        'linked_price_tier_id', 'is_active', 'sort_order', 'customers_count',
    ];

    protected function casts(): array
    {
        return [
            'is_auto_assign'        => 'boolean',
            'is_active'             => 'boolean',
            'auto_assign_condition' => 'array',
        ];
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_customer_tag');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function linkedPriceTier()
    {
        return $this->belongsTo(PriceTier::class, 'linked_price_tier_id');
    }
}
