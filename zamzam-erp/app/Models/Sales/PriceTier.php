<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;

class PriceTier extends Model
{
    protected $fillable = [
        'name', 'code', 'description',
        'discount_percent', 'is_default', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'decimal:2',
            'is_default'       => 'boolean',
            'is_active'        => 'boolean',
        ];
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
