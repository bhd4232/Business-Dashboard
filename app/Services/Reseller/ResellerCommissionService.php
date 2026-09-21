<?php

namespace App\Services\Reseller;

use App\Models\Company;
use App\Models\Order;
use App\Models\ResellerCommission;
use App\Models\ResellerProduct;
use App\Services\AuditLogService;
use RuntimeException;

/**
 * Reseller-side half of 10_INVESTOR_RESELLER_AUTO_PAYOUT_PLAN.md. Owner
 * (2026-09-17): commission = the order's actual sale price minus a flat
 * per-reseller-per-product wholesale rate (App\Models\ResellerProduct::
 * wholesale_rate, staff-set), minus itemized costs (packaging, return
 * handling, courier/delivery -- staff-entered, since none of these are
 * reliably known until fulfillment); becomes payable after a configurable
 * hold period (default 3 days) with no return.
 */
class ResellerCommissionService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * Creates the holding-period commission record the moment an order is
     * marked delivered (App\Observers\ResellerCommissionObserver). A no-op
     * for a non-reseller order or one that already has a commission record
     * (a re-delivery/duplicate webhook must never create a second one).
     */
    public function createForDeliveredOrder(Order $order): ?ResellerCommission
    {
        if (! $order->reseller_customer_id) {
            return null;
        }

        if (ResellerCommission::query()->where('order_id', $order->id)->exists()) {
            return null;
        }

        $wholesaleRates = ResellerProduct::query()
            ->where('customer_id', $order->reseller_customer_id)
            ->pluck('wholesale_rate', 'product_id');

        $grossMargin = 0.0;

        foreach ($order->items as $item) {
            $rate = $wholesaleRates->get($item->product_id);

            // No wholesale rate set for this product yet -- contributes
            // nothing rather than guessing one. Staff can still adjust the
            // commission's cost_breakdown/amount by hand before it's paid.
            if ($rate === null) {
                continue;
            }

            $grossMargin += ((float) $item->unit_price - (float) $rate) * (int) $item->quantity;
        }

        $grossMargin = max(0.0, round($grossMargin, 2));
        $holdDays = (int) ($order->company?->payoutSetting?->reseller_commission_hold_days ?? 3);

        $commission = ResellerCommission::query()->create([
            'company_id' => $order->company_id,
            'order_id' => $order->id,
            'reseller_customer_id' => $order->reseller_customer_id,
            'gross_margin_amount' => $grossMargin,
            'other_costs_amount' => 0,
            'commission_amount' => $grossMargin,
            'status' => 'holding',
            'order_delivered_at' => now()->toDateString(),
            'holding_until' => now()->addDays($holdDays)->toDateString(),
        ]);

        $this->audit->record('reseller_commission_created', $commission, null, [
            'order_id' => $order->id, 'gross_margin_amount' => $grossMargin, 'holding_until' => $commission->holding_until->toDateString(),
        ]);

        return $commission;
    }

    /**
     * @param  list<array{label: string, amount: float}>  $costBreakdown
     */
    public function updateCostBreakdown(ResellerCommission $commission, array $costBreakdown): ResellerCommission
    {
        if (! in_array($commission->status, ['holding', 'payable'], true)) {
            throw new RuntimeException("Only a holding or payable commission can be adjusted (currently '{$commission->status}').");
        }

        $otherCosts = round(array_sum(array_map(fn (array $row): float => (float) $row['amount'], $costBreakdown)), 2);

        $commission->update([
            'cost_breakdown' => $costBreakdown,
            'other_costs_amount' => $otherCosts,
            'commission_amount' => max(0.0, round((float) $commission->gross_margin_amount - $otherCosts, 2)),
        ]);

        return $commission;
    }

    /** Promotes every commission whose hold period has elapsed, for one company. Called per-company by the scheduled command. */
    public function promoteDueToPayable(Company $company): int
    {
        $due = ResellerCommission::query()
            ->where('company_id', $company->id)
            ->where('status', 'holding')
            ->where('holding_until', '<=', now()->toDateString())
            ->get();

        foreach ($due as $commission) {
            $commission->update(['status' => 'payable', 'promoted_to_payable_at' => now()]);
            $this->audit->record('reseller_commission_promoted', $commission);
        }

        return $due->count();
    }

    /**
     * Reverses a commission still in its hold window (or, rarer, already
     * payable but not yet batched) when its order is returned/refunded. A
     * commission already in_batch or paid is deliberately left untouched --
     * clawing back money already sent needs an owner-confirmed recovery
     * rule this plan doesn't have yet (see 10_INVESTOR_RESELLER_AUTO_
     * PAYOUT_PLAN.md, "Commission পাঠানোর পরে... return হলে"); this case is
     * only flagged in the audit log for manual follow-up.
     */
    public function reverseForOrder(Order $order, string $reason): void
    {
        $commission = ResellerCommission::query()->where('order_id', $order->id)->first();

        if (! $commission) {
            return;
        }

        if (! in_array($commission->status, ['holding', 'payable'], true)) {
            $this->audit->record('reseller_commission_return_after_payout', $commission, null, [
                'order_id' => $order->id, 'reason' => $reason, 'commission_status' => $commission->status,
            ]);

            return;
        }

        $commission->update(['status' => 'reversed', 'reversed_at' => now(), 'reversal_reason' => $reason]);
        $this->audit->record('reseller_commission_reversed', $commission, null, ['reason' => $reason]);
    }
}
