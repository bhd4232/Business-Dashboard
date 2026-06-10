<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'is_base',
        'is_active',
        'decimal_places',
    ];

    protected function casts(): array
    {
        return [
            'is_base'   => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
