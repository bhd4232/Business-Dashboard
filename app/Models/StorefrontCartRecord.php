<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class StorefrontCartRecord extends Model
{
    use BelongsToCompany;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CONVERTED = 'converted';

    public const STATUS_REMINDED = 'reminded';

    protected $fillable = [
        'company_id',
        'session_id',
        'phone',
        'customer_name',
        'items',
        'status',
        'reminded_at',
    ];

    protected $casts = [
        'items' => 'array',
        'reminded_at' => 'datetime',
    ];
}
