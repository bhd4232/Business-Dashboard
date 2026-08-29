<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Deliberately does NOT use BelongsToCompany/CompanyScope, and is excluded
 * from MultiCompanyIsolationTest's contract on purpose -- see
 * App\Models\MobileCrashReport for the identical precedent.
 *
 * A deploy/migration failure happens before any company context exists (it
 * runs once, for the whole app, as the container boots -- see
 * App\Console\Commands\DeployMigrate). It is a platform-level diagnostics
 * log, visible to super admins only via its Filament resource -- there is no
 * per-company data here to leak.
 */
class DeploymentError extends Model
{
    protected $fillable = [
        'source',
        'message',
        'details',
        'context',
        'occurred_at',
    ];

    protected $casts = [
        'context' => 'array',
        'occurred_at' => 'datetime',
    ];
}
