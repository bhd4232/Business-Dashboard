<?php

namespace App\Models;

use App\Services\CustomerRiskService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CustomerBlacklist extends Model
{
    protected $fillable = ['company_id', 'phone', 'address', 'reason', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (CustomerBlacklist $blacklist): void {
            if (blank($blacklist->phone) && blank($blacklist->address)) {
                throw ValidationException::withMessages(['phone' => 'A phone number or address is required.']);
            }
            $blacklist->created_by ??= Auth::id();
            $blacklist->phone = filled($blacklist->phone)
                ? app(CustomerRiskService::class)->normalizePhone($blacklist->phone)
                : null;
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
