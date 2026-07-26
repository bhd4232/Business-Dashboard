<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PushDevice extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'token_hash',
        'device_id',
        'platform',
        'app_version',
        'is_active',
        'last_seen_at',
        'disabled_at',
        'last_error_code',
        'last_error_at',
    ];

    protected $hidden = [
        'token',
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'disabled_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appUpdateDeliveries(): HasMany
    {
        return $this->hasMany(AppUpdatePushDelivery::class);
    }
}
