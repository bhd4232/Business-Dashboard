<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Deliberately does NOT use BelongsToCompany/CompanyScope, and is excluded
 * from MultiCompanyIsolationTest's contract on purpose.
 *
 * A crash reported by the Android app can happen before login -- there is no
 * authenticated user and no company context to attach it to at all (unlike
 * CustomerBlacklist, which is sometimes global but always has the option of
 * a company_id; this model never does). It's a single-tenant diagnostics log
 * for this native app build, visible to super admins only via its Filament
 * resource -- there is no per-company data here to leak.
 */
class MobileCrashReport extends Model
{
    protected $fillable = [
        'exception_class',
        'message',
        'stack_trace',
        'app_version_name',
        'app_version_code',
        'os_version',
        'device_manufacturer',
        'device_model',
        'occurred_at',
        'ip_address',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];
}
