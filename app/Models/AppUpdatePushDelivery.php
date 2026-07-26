<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppUpdatePushDelivery extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_STALE = 'stale';

    protected $fillable = [
        'push_device_id',
        'deployment_id',
        'release_version',
        'status',
        'attempts',
        'last_error_code',
        'last_error',
        'attempted_at',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'attempted_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function pushDevice(): BelongsTo
    {
        return $this->belongsTo(PushDevice::class);
    }
}
