<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Reseller\ResellerCommissionService;
use Illuminate\Console\Command;

/**
 * Promotes every reseller commission whose hold period has elapsed
 * (holding -> payable) so it becomes eligible for a payout batch. See
 * 10_INVESTOR_RESELLER_AUTO_PAYOUT_PLAN.md and
 * App\Services\Reseller\ResellerCommissionService.
 */
class PromoteResellerCommissions extends Command
{
    protected $signature = 'reseller-commissions:promote';

    protected $description = 'Promote reseller commissions past their hold period from holding to payable';

    public function handle(ResellerCommissionService $commissions): int
    {
        $promoted = 0;

        foreach (Company::query()->where('is_active', true)->get() as $company) {
            $promoted += $commissions->promoteDueToPayable($company);
        }

        $this->info("Reseller commissions promoted to payable: {$promoted}.");

        return self::SUCCESS;
    }
}
