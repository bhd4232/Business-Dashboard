<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per company — see 10_INVESTOR_RESELLER_AUTO_PAYOUT_PLAN.md.
 * `disbursing_bank_details` / `beftn_api_credentials` are encrypted JSON
 * blobs (same `encrypted:array` pattern as StorefrontSetting::payment_
 * credentials) so the app never stores a bank account number in plaintext.
 */
class CompanyPayoutSetting extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'disbursing_bank_details', 'beftn_integration_mode', 'beftn_api_credentials',
        'reseller_commission_hold_days',
    ];

    protected $casts = [
        'disbursing_bank_details' => 'encrypted:array',
        'beftn_api_credentials' => 'encrypted:array',
        'reseller_commission_hold_days' => 'integer',
    ];
}
