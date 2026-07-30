<?php

namespace App\Services\Investment;

use App\Models\ChannelPartnerPayout;
use App\Models\InvestmentProject;
use App\Models\ProjectSettlement;
use App\Models\SettlementPayout;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SettlementService
{
    public function calculateAndSettle(InvestmentProject $project, float $totalRevenue, int $settledByUserId): ProjectSettlement
    {
        if ($project->status !== 'closed') {
            throw new RuntimeException('Only a closed project can be settled.');
        }

        if ($totalRevenue < 0) {
            throw new RuntimeException('Total revenue cannot be negative.');
        }

        return DB::transaction(function () use ($project, $totalRevenue, $settledByUserId): ProjectSettlement {
            $lockedProject = InvestmentProject::query()->whereKey($project)->lockForUpdate()->firstOrFail();

            if ($lockedProject->settlement()->exists()) {
                throw new RuntimeException('This project already has a settlement.');
            }

            $investments = $lockedProject->investments()->with('investor')->get();
            $totalInvested = round((float) $investments->sum('amount'), 2);

            if ($totalInvested <= 0) {
                throw new RuntimeException('At least one investment is required before settlement.');
            }

            $totalCost = round($lockedProject->totalCostItems(), 2);
            $netProfit = round($totalRevenue - $totalCost, 2);

            if ($netProfit < 0) {
                throw new RuntimeException('Net profit is negative. Loss handling must be agreed before settlement.');
            }

            $investorPool = round($netProfit * ((float) $lockedProject->investor_share_percent / 100), 2);
            $configuredChannelPool = round($netProfit * ((float) $lockedProject->channel_partner_share_percent / 100), 2);
            $partnerInvested = round((float) $investments->filter(fn ($investment) => filled($investment->investor->channel_partner_id))->sum('amount'), 2);
            $channelPayout = round($configuredChannelPool * ($partnerInvested / $totalInvested), 2);
            $companyNet = round($netProfit - $investorPool - $channelPayout, 2);
            $annualizedReturn = $lockedProject->trade_cycle_days
                ? round(($netProfit / $totalInvested) * (365 / $lockedProject->trade_cycle_days) * 100, 2)
                : null;

            $settlement = ProjectSettlement::query()->create([
                'company_id' => $lockedProject->company_id,
                'project_id' => $lockedProject->id,
                'total_revenue' => $totalRevenue,
                'total_cost' => $totalCost,
                'net_profit' => $netProfit,
                'investor_pool_amount' => $investorPool,
                'channel_partner_amount' => $channelPayout,
                'company_net_amount' => $companyNet,
                'annualized_return_percent' => $annualizedReturn,
                'status' => 'confirmed',
                'settled_by' => $settledByUserId,
                'settled_at' => now(),
            ]);

            $grouped = $investments->groupBy('investor_id');
            $allocatedProfit = 0.0;
            $lastInvestorId = $grouped->keys()->last();

            foreach ($grouped as $investorId => $rows) {
                $principal = round((float) $rows->sum('amount'), 2);
                $profitShare = (int) $investorId === (int) $lastInvestorId
                    ? round($investorPool - $allocatedProfit, 2)
                    : round($investorPool * ($principal / $totalInvested), 2);
                $allocatedProfit += $profitShare;

                SettlementPayout::query()->create([
                    'company_id' => $lockedProject->company_id,
                    'settlement_id' => $settlement->id,
                    'investor_id' => $investorId,
                    'investment_amount' => $principal,
                    'profit_share_amount' => $profitShare,
                    'total_payout' => round($principal + $profitShare, 2),
                    'recipient_name' => $rows->first()->investor->name,
                ]);
            }

            $partnerIds = $investments->pluck('investor.channel_partner_id')->filter()->unique()->values();

            if ($partnerIds->count() > 1) {
                throw new RuntimeException('A project currently supports only one channel partner.');
            }

            $partnerId = $partnerIds->first();

            if ($partnerId && $channelPayout > 0) {
                ChannelPartnerPayout::query()->create([
                    'company_id' => $lockedProject->company_id,
                    'settlement_id' => $settlement->id,
                    'investor_id' => $partnerId,
                    'amount' => $channelPayout,
                ]);
            }

            $lockedProject->forceFill(['status' => 'settled'])->saveQuietly();

            app(AuditLogService::class)->record('project_settled', $settlement, null, [
                'project_id' => $lockedProject->id,
                'total_revenue' => $totalRevenue,
                'total_cost' => $totalCost,
                'net_profit' => $netProfit,
            ]);

            return $settlement->load(['payouts', 'channelPartnerPayouts']);
        });
    }
}
