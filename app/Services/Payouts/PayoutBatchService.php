<?php

namespace App\Services\Payouts;

use App\Models\ChannelPartnerPayout;
use App\Models\Company;
use App\Models\PayoutBatch;
use App\Models\PayoutItem;
use App\Models\ResellerCommission;
use App\Models\SettlementPayout;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\CompanyStorageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The shared bank/MFS disbursement engine — see
 * 10_INVESTOR_RESELLER_AUTO_PAYOUT_PLAN.md. Feeds from the Investor/
 * Mudarabah module's pending SettlementPayout/ChannelPartnerPayout rows
 * (createBatchFromPendingInvestorPayouts(), always method='beftn_bank') and,
 * separately, from payable ResellerCommission rows
 * (createBatchFromPendingResellerCommissions(), any method). The two are
 * deliberately not combined into one call — an Investor batch and a
 * Reseller batch due the same day are generated as two separate batches in
 * this round; combining them into one BEFTN file is a possible later
 * simplification, not required for either to work correctly.
 *
 * State machine: draft -> approved -> file_generated -> submitted_to_bank ->
 * completed | partially_completed | failed; draft -> cancelled. Every
 * transition is a deliberate, permission-gated action — nothing here ever
 * moves a batch to "submitted_to_bank" or beyond on its own, and generating
 * the export file always requires a prior, separate approval.
 */
class PayoutBatchService
{
    public function __construct(
        private readonly PayoutFormatterResolver $formatters,
        private readonly CompanyStorageService $storage,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * Pulls every pending Investor settlement payout and channel-partner
     * payout for the company that (a) is not already sitting in another
     * active batch and (b) has complete bank details, and groups them into
     * one new draft batch. Pending payouts with incomplete bank details are
     * silently skipped — they still show as "pending" on their own resource
     * and can be batched once their bank details are filled in.
     */
    public function createBatchFromPendingInvestorPayouts(Company $company, User $generatedBy): PayoutBatch
    {
        return DB::transaction(function () use ($company, $generatedBy): PayoutBatch {
            $settlementPayouts = $this->eligibleSettlementPayouts($company)->get();
            $channelPayouts = $this->eligibleChannelPartnerPayouts($company)->get();

            if ($settlementPayouts->isEmpty() && $channelPayouts->isEmpty()) {
                throw new RuntimeException('No pending investor payouts have complete bank details to batch.');
            }

            $batch = PayoutBatch::query()->create([
                'company_id' => $company->id,
                'batch_number' => $this->nextBatchNumber($company),
                'method' => 'beftn_bank',
                'status' => 'draft',
                'generated_by' => $generatedBy->id,
            ]);

            $total = 0.0;
            $count = 0;

            foreach ($settlementPayouts as $payout) {
                $this->attachItem($batch, $payout, (float) $payout->total_payout, $payout->recipient_name);
                $total += (float) $payout->total_payout;
                $count++;
            }

            foreach ($channelPayouts as $payout) {
                $this->attachItem($batch, $payout, (float) $payout->amount, $payout->recipient_name ?: $payout->investor->name);
                $total += (float) $payout->amount;
                $count++;
            }

            $batch->update(['total_amount' => $total, 'total_items' => $count]);
            $this->audit->record('payout_batch_created', $batch, null, ['total_amount' => $total, 'total_items' => $count]);

            return $batch->fresh('items');
        });
    }

    /**
     * Pulls every payable ResellerCommission for the company whose reseller
     * chose this exact payout method ('bank' or an 'mfs_*' value — matches
     * Customer::reseller_payout_method) and has complete payout details,
     * skipping incomplete ones the same way the investor batch does.
     */
    public function createBatchFromPendingResellerCommissions(Company $company, string $method, User $generatedBy): PayoutBatch
    {
        return DB::transaction(function () use ($company, $method, $generatedBy): PayoutBatch {
            $commissions = $this->eligibleResellerCommissions($company, $method)
                ->get()
                ->filter(fn (ResellerCommission $commission): bool => $commission->hasCompletePayoutDetails());

            if ($commissions->isEmpty()) {
                throw new RuntimeException("No payable reseller commissions have complete '{$method}' payout details to batch.");
            }

            $batchMethod = $method === 'bank' ? 'beftn_bank' : $method;

            $batch = PayoutBatch::query()->create([
                'company_id' => $company->id,
                'batch_number' => $this->nextBatchNumber($company),
                'method' => $batchMethod,
                'status' => 'draft',
                'generated_by' => $generatedBy->id,
            ]);

            $total = 0.0;
            $count = 0;

            foreach ($commissions as $commission) {
                $this->attachResellerCommissionItem($batch, $commission);
                $total += (float) $commission->commission_amount;
                $count++;
            }

            $batch->update(['total_amount' => $total, 'total_items' => $count]);
            $this->audit->record('payout_batch_created', $batch, null, ['total_amount' => $total, 'total_items' => $count, 'source' => 'reseller_commissions']);

            return $batch->fresh('items');
        });
    }

    public function approve(PayoutBatch $batch, User $approvedBy): PayoutBatch
    {
        $this->assertStatus($batch, 'draft', 'approved');

        $batch->update(['status' => 'approved', 'approved_by' => $approvedBy->id, 'approved_at' => now()]);
        $this->audit->record('payout_batch_approved', $batch);

        return $batch;
    }

    public function cancel(PayoutBatch $batch): PayoutBatch
    {
        $this->assertStatus($batch, 'draft', 'cancelled');

        DB::transaction(function () use ($batch): void {
            $batch->items()->delete();
            $batch->update(['status' => 'cancelled', 'total_amount' => 0, 'total_items' => 0]);
        });
        $this->audit->record('payout_batch_cancelled', $batch);

        return $batch;
    }

    /** Generates the export file and stores it on the company's private disk; the batch and every item move to file_generated/in_batch together. */
    public function generateExportFile(PayoutBatch $batch): PayoutBatch
    {
        $this->assertStatus($batch, 'approved', 'file_generated');

        $items = $batch->items()->get();
        $contents = $this->formatters->forMethod($batch->method)->generate($batch, $items);
        $path = $this->storage->putPrivate($batch->company, 'payout-batches', "{$batch->batch_number}.csv", $contents);

        DB::transaction(function () use ($batch, $path): void {
            $batch->update(['export_file_path' => $path, 'status' => 'file_generated']);
            $batch->items()->update(['status' => 'in_batch']);
        });

        $this->audit->record('payout_batch_file_generated', $batch, null, ['export_file_path' => $path]);

        return $batch;
    }

    public function markSubmitted(PayoutBatch $batch, ?string $bankBatchReference): PayoutBatch
    {
        $this->assertStatus($batch, 'file_generated', 'submitted_to_bank');

        $batch->update([
            'status' => 'submitted_to_bank',
            'submitted_at' => now(),
            'bank_batch_reference' => $bankBatchReference,
        ]);
        $this->audit->record('payout_batch_submitted', $batch, null, ['bank_batch_reference' => $bankBatchReference]);

        return $batch;
    }

    /**
     * Reconciles one item against the bank statement/portal report — 'paid'
     * syncs the underlying SettlementPayout/ChannelPartnerPayout to paid,
     * 'failed' leaves it pending so it can be corrected and re-batched.
     * Recomputes the batch's overall status once the item is saved.
     */
    public function reconcileItem(PayoutItem $item, string $status, ?string $bankTransactionReference = null, ?string $failureReason = null): PayoutItem
    {
        if (! in_array($status, ['paid', 'failed'], true)) {
            throw new RuntimeException("Invalid reconcile status: {$status}.");
        }

        $batch = $item->batch;
        if ($batch->status !== 'submitted_to_bank') {
            throw new RuntimeException('Only items in a batch that was marked submitted to the bank can be reconciled.');
        }

        DB::transaction(function () use ($item, $status, $bankTransactionReference, $failureReason, $batch): void {
            $item->update([
                'status' => $status,
                'bank_transaction_reference' => $bankTransactionReference,
                'failure_reason' => $status === 'failed' ? $failureReason : null,
                'paid_at' => $status === 'paid' ? now() : null,
            ]);

            if ($status === 'paid') {
                $this->markPayablePaid($item->payable, $bankTransactionReference);
            }

            $this->recomputeBatchStatus($batch);
        });

        $this->audit->record('payout_item_reconciled', $item, null, ['status' => $status, 'bank_transaction_reference' => $bankTransactionReference]);

        return $item->fresh();
    }

    private function recomputeBatchStatus(PayoutBatch $batch): void
    {
        $statuses = $batch->items()->pluck('status');

        if ($statuses->contains('in_batch')) {
            return; // still waiting on the rest to be reconciled
        }

        $status = match (true) {
            $statuses->every(fn (string $s): bool => $s === 'paid') => 'completed',
            $statuses->contains('paid') => 'partially_completed',
            default => 'failed',
        };

        $batch->update(['status' => $status, 'completed_at' => in_array($status, ['completed', 'partially_completed', 'failed'], true) ? now() : null]);
    }

    private function markPayablePaid(Model $payable, ?string $reference): void
    {
        if ($payable instanceof ResellerCommission) {
            $payable->update(['status' => 'paid', 'paid_at' => now()]);

            return;
        }

        $payable->update([
            'payment_status' => 'paid',
            'paid_at' => now()->toDateString(),
            'payment_method' => 'bank',
            'payment_reference' => $reference,
        ]);
    }

    private function attachResellerCommissionItem(PayoutBatch $batch, ResellerCommission $commission): PayoutItem
    {
        $reseller = $commission->resellerCustomer;
        $details = $reseller->reseller_payout_details ?? [];

        return PayoutItem::query()->create([
            'company_id' => $batch->company_id,
            'payout_batch_id' => $batch->id,
            'payable_type' => ResellerCommission::class,
            'payable_id' => $commission->id,
            'recipient_name' => $reseller->name,
            'recipient_bank_name' => $details['bank_name'] ?? null,
            'recipient_branch' => $details['branch'] ?? null,
            'recipient_routing_number' => $details['routing_number'] ?? null,
            'recipient_account_number' => $details['account_number'] ?? null,
            'recipient_mfs_number' => $details['msisdn'] ?? null,
            'amount' => (float) $commission->commission_amount,
            'status' => 'pending',
        ]);
    }

    private function eligibleResellerCommissions(Company $company, string $method): Builder
    {
        return ResellerCommission::query()
            ->where('company_id', $company->id)
            ->where('status', 'payable')
            ->whereDoesntHave('payoutItems', fn ($q) => $q->whereIn('status', PayoutItem::ACTIVE_STATUSES))
            ->whereHas('resellerCustomer', fn ($q) => $q->where('reseller_payout_method', $method))
            ->with('resellerCustomer');
    }

    private function attachItem(PayoutBatch $batch, SettlementPayout|ChannelPartnerPayout $payable, float $amount, string $recipientName): PayoutItem
    {
        return PayoutItem::query()->create([
            'company_id' => $batch->company_id,
            'payout_batch_id' => $batch->id,
            'payable_type' => $payable::class,
            'payable_id' => $payable->id,
            'recipient_name' => $recipientName,
            'recipient_bank_name' => $payable->recipient_bank_name,
            'recipient_branch' => $payable->recipient_branch,
            'recipient_routing_number' => $payable->recipient_routing_number,
            'recipient_account_number' => $payable->recipient_account_number,
            'amount' => $amount,
            'status' => 'pending',
        ]);
    }

    private function eligibleSettlementPayouts(Company $company): Builder
    {
        return SettlementPayout::query()
            ->where('company_id', $company->id)
            ->where('payment_status', 'pending')
            ->whereNotNull('recipient_bank_name')
            ->whereNotNull('recipient_routing_number')
            ->whereNotNull('recipient_account_number')
            ->whereDoesntHave('payoutItems', fn ($q) => $q->whereIn('status', PayoutItem::ACTIVE_STATUSES));
    }

    private function eligibleChannelPartnerPayouts(Company $company): Builder
    {
        return ChannelPartnerPayout::query()
            ->where('company_id', $company->id)
            ->where('payment_status', 'pending')
            ->whereNotNull('recipient_bank_name')
            ->whereNotNull('recipient_routing_number')
            ->whereNotNull('recipient_account_number')
            ->whereDoesntHave('payoutItems', fn ($q) => $q->whereIn('status', PayoutItem::ACTIVE_STATUSES))
            ->with('investor');
    }

    private function nextBatchNumber(Company $company): string
    {
        $today = now()->format('Ymd');
        $sequenceToday = PayoutBatch::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('batch_number', 'like', "PB-{$today}-%")
            ->count() + 1;

        return "PB-{$today}-".str_pad((string) $sequenceToday, 3, '0', STR_PAD_LEFT);
    }

    private function assertStatus(PayoutBatch $batch, string $expected, string $forAction): void
    {
        if ($batch->status !== $expected) {
            throw new RuntimeException("Batch must be '{$expected}' before it can move to '{$forAction}' (currently '{$batch->status}').");
        }
    }
}
