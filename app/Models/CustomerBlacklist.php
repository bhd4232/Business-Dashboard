<?php

namespace App\Models;

use App\Services\CustomerRiskService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Deliberately does NOT use BelongsToCompany/CompanyScope, and is excluded
 * from MultiCompanyIsolationTest's contract on purpose.
 *
 * A blacklist entry is either global (`company_id = NULL`, applies to every
 * company) or scoped to one company. `CustomerRiskService::blacklistMatch()`
 * queries it with an explicit `whereNull('company_id')->orWhere('company_id', ...)`
 * so both kinds are honoured. Adding the scope would hide global entries and
 * break that feature. The Filament resource is super-admin only, so there is
 * no cross-company leak through the admin panel. Any new query against this
 * model must filter company_id explicitly (global + owning company).
 */
class CustomerBlacklist extends Model
{
    protected $fillable = ['company_id', 'phone', 'email', 'ip_address', 'address', 'reason', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (CustomerBlacklist $blacklist): void {
            if (blank($blacklist->phone) && blank($blacklist->email) && blank($blacklist->ip_address) && blank($blacklist->address)) {
                throw ValidationException::withMessages(['phone' => 'A phone number, email, IP address, or address is required.']);
            }
            $blacklist->created_by ??= Auth::id();
            $blacklist->phone = filled($blacklist->phone)
                ? app(CustomerRiskService::class)->normalizePhone($blacklist->phone)
                : null;
            $blacklist->email = filled($blacklist->email) ? mb_strtolower(trim($blacklist->email)) : null;

            if (filled($blacklist->ip_address) && filter_var($blacklist->ip_address, FILTER_VALIDATE_IP) === false) {
                throw ValidationException::withMessages(['ip_address' => 'Enter a valid IPv4 or IPv6 address.']);
            }

            $blacklist->ip_address = filled($blacklist->ip_address) ? trim($blacklist->ip_address) : null;
            $blacklist->address = filled($blacklist->address) ? mb_strtolower(trim($blacklist->address)) : null;
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
