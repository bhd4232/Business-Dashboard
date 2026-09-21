<?php

namespace App\Contracts;

use App\Models\PayoutBatch;
use App\Models\PayoutItem;
use Illuminate\Support\Collection;

/**
 * Turns a PayoutBatch's items into the bytes to hand to a bank. There is no
 * universal cross-bank BEFTN format in Bangladesh, so App\Services\Payouts\
 * GenericBeftnCsvFormatter is the only implementation right now (a plain CSV
 * the owner re-keys/uploads into their bank's own corporate portal). Once a
 * bank confirms its exact file spec or an API, a `<BankName>BeftnFormatter`
 * implementing this same contract can replace it in App\Providers\
 * AppServiceProvider's binding without any change to PayoutBatchService —
 * see 10_INVESTOR_RESELLER_AUTO_PAYOUT_PLAN.md, Phase 2.
 */
interface BeftnFileFormatter
{
    /**
     * @param  Collection<int, PayoutItem>  $items
     * @return string raw file contents to write to disk
     */
    public function generate(PayoutBatch $batch, Collection $items): string;
}
