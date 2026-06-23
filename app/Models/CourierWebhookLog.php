<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierWebhookLog extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'courier_provider_id',
        'event',
        'delivery_id',
        'payload',
        'status',
        'attempts',
        'error',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(CourierProvider::class, 'courier_provider_id');
    }
}
