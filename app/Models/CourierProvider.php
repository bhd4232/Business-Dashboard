<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CourierProvider extends Model
{
    use BelongsToCompany;

    public const DRIVER_MANUAL = 'manual';

    public const DRIVER_STEADFAST = 'steadfast';

    public const DRIVER_PATHAO = 'pathao';

    public const DRIVER_REDX = 'redx';

    public const DRIVER_ECOURIER = 'ecourier';

    public const DRIVERS = [
        self::DRIVER_MANUAL => 'Custom',
        self::DRIVER_STEADFAST => 'Steadfast',
        self::DRIVER_PATHAO => 'Pathao',
        self::DRIVER_REDX => 'RedX',
        self::DRIVER_ECOURIER => 'E-Courier',
    ];

    public const API_DRIVERS = [
        self::DRIVER_STEADFAST,
        self::DRIVER_PATHAO,
        self::DRIVER_REDX,
        self::DRIVER_ECOURIER,
    ];

    public const MONITORING_DEFAULTS = [
        'stale_after_days' => 5,
        'sync_failure_alert_threshold' => 3,
        'sync_batch_limit' => 50,
        'sync_cooldown_minutes' => 25,
    ];

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'driver',
        'credentials',
        'settings',
        'is_active',
        'sync_failure_count',
        'last_sync_error',
        'last_synced_at',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'settings' => 'array',
        'is_active' => 'boolean',
        'sync_failure_count' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (CourierProvider $provider): void {
            $provider->slug = $provider->slug ?: Str::slug($provider->name);
            $provider->driver = $provider->driver ?: self::DRIVER_MANUAL;
        });
    }

    public function monitoringSetting(string $key): int
    {
        $value = (int) (($this->settings ?? [])[$key] ?? 0);

        return $value > 0 ? $value : (self::MONITORING_DEFAULTS[$key] ?? 0);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(CourierBooking::class);
    }
}
