<?php

namespace App\Services\Payouts;

use App\Contracts\BeftnFileFormatter;

/**
 * Picks the export-file formatter for a batch's method. Only one
 * implementation exists per rail today (GenericBeftnCsvFormatter for
 * 'beftn_bank', GenericMfsListFormatter for every 'mfs_*' method) — see
 * App\Contracts\BeftnFileFormatter for why, and
 * 10_INVESTOR_RESELLER_AUTO_PAYOUT_PLAN.md Phase 2/3 for what replaces them.
 */
class PayoutFormatterResolver
{
    public function forMethod(string $method): BeftnFileFormatter
    {
        return str_starts_with($method, 'mfs_')
            ? app(GenericMfsListFormatter::class)
            : app(GenericBeftnCsvFormatter::class);
    }
}
