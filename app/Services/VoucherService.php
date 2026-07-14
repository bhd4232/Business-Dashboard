<?php

namespace App\Services;

use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\FundSource;
use App\Models\Purchase;
use App\Models\SupplierPayment;
use App\Models\TransactionLedger;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Core Voucher & Fund Control workflow.
 *
 * This is a self-contained approval flow (pending -> verified -> approved /
 * rejected / cancelled), not yet wired into a shared ApprovalGateService —
 * that service does not exist in this codebase yet (it belongs to the
 * not-yet-built Task/Approval Workflow module). Per the module plan's own
 * fallback option, this ships with simple inline approval logic now so the
 * accounting rules land safely; migrating verify()/approve() onto a shared
 * ApprovalGateService later is a self-contained follow-up.
 *
 * Rule 1 (never violate): inventory_purchase, capital_investment,
 * owner_withdrawal, asset_purchase, loan, and fund_transfer transaction
 * types NEVER create an Expense record — they move funds between a Fund
 * Source/Account and an asset/liability, they are not spend.
 */
class VoucherService
{
    /**
     * transaction_types that require an account_id (the account money moves
     * into/out of) at submission time.
     */
    protected const ACCOUNT_REQUIRED_TYPES = [
        'business_expense', 'supplier_payment', 'customer_payment',
        'capital_investment', 'owner_withdrawal', 'refund',
        'asset_purchase', 'loan', 'other',
    ];

    public function submit(array $data, User $user): Voucher
    {
        $type = $data['type'] ?? null;
        $transactionType = $data['transaction_type'] ?? null;

        if ($transactionType === 'fund_transfer') {
            throw ValidationException::withMessages([
                'transaction_type' => 'Use the Fund Transfer screen for moving money between your own accounts, not a voucher.',
            ]);
        }

        if ((float) ($data['amount'] ?? 0) <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }

        if ($transactionType === 'inventory_purchase') {
            if (empty($data['purchase_id']) || empty($data['fund_source_id'])) {
                throw ValidationException::withMessages([
                    'fund_source_id' => 'An inventory purchase voucher requires both a purchase and a funding source.',
                ]);
            }
        } elseif (in_array($transactionType, self::ACCOUNT_REQUIRED_TYPES, true) && empty($data['account_id'])) {
            throw ValidationException::withMessages([
                'account_id' => 'Please select the account this voucher moves money through.',
            ]);
        }

        if ($transactionType === 'customer_payment' && empty($data['customer_id'])) {
            throw ValidationException::withMessages(['customer_id' => 'Please select a customer.']);
        }

        if ($transactionType === 'supplier_payment' && empty($data['supplier_id'])) {
            throw ValidationException::withMessages(['supplier_id' => 'Please select a supplier.']);
        }

        if ($transactionType === 'business_expense' && empty($data['expense_category_id'])) {
            throw ValidationException::withMessages(['expense_category_id' => 'Please select an expense category.']);
        }

        return Voucher::query()->create([
            ...$data,
            'voucher_number' => Voucher::nextVoucherNumber($type),
            'status' => Voucher::STATUS_PENDING,
            'submitted_by' => $user->getKey(),
        ]);
    }

    public function verify(Voucher $voucher, User $user): void
    {
        if (! $voucher->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Only a pending voucher can be verified.',
            ]);
        }

        $voucher->update([
            'status' => Voucher::STATUS_VERIFIED,
            'verified_by' => $user->getKey(),
            'verified_at' => now(),
        ]);
    }

    /**
     * A credit voucher must be verified first (money received needs a
     * second set of eyes on the evidence before it's booked). A debit
     * voucher may be approved straight from pending, or from verified if it
     * went through that optional step.
     */
    public function approve(Voucher $voucher, User $user): void
    {
        $allowedFrom = $voucher->isCredit()
            ? [Voucher::STATUS_VERIFIED]
            : [Voucher::STATUS_PENDING, Voucher::STATUS_VERIFIED];

        if (! in_array($voucher->status, $allowedFrom, true)) {
            throw ValidationException::withMessages([
                'status' => $voucher->isCredit()
                    ? 'A credit voucher must be verified before it can be approved.'
                    : 'This voucher cannot be approved from its current status.',
            ]);
        }

        if ($voucher->transaction_type === 'inventory_purchase') {
            $this->assertFundingDoesNotExceedPurchaseTotal($voucher);
        }

        DB::transaction(function () use ($voucher, $user): void {
            $resulting = $this->applyAccountingEffect($voucher);

            $voucher->update([
                'status' => Voucher::STATUS_APPROVED,
                'approved_by' => $user->getKey(),
                'approved_at' => now(),
                'resulting_model_type' => $resulting ? $resulting::class : null,
                'resulting_model_id' => $resulting?->getKey(),
            ]);
        });
    }

    public function reject(Voucher $voucher, string $reason, User $user): void
    {
        if (in_array($voucher->status, [Voucher::STATUS_APPROVED, Voucher::STATUS_REJECTED, Voucher::STATUS_CANCELLED], true)) {
            throw ValidationException::withMessages([
                'status' => 'This voucher has already reached a final status.',
            ]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'rejection_reason' => 'A rejection reason is required.',
            ]);
        }

        $voucher->update([
            'status' => Voucher::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'approved_by' => $user->getKey(),
            'approved_at' => now(),
        ]);
    }

    public function cancel(Voucher $voucher): void
    {
        if (! $voucher->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Only a pending voucher can be cancelled.',
            ]);
        }

        $voucher->update(['status' => Voucher::STATUS_CANCELLED]);
    }

    /**
     * Funding vouchers for one Purchase (across possibly multiple fund
     * sources) must never sum past that purchase's total — otherwise more
     * money would be recorded as spent on it than it actually cost.
     */
    protected function assertFundingDoesNotExceedPurchaseTotal(Voucher $voucher): void
    {
        $purchase = Purchase::query()->find($voucher->purchase_id);

        if (! $purchase) {
            return;
        }

        $alreadyFunded = (float) Voucher::query()
            ->where('purchase_id', $purchase->getKey())
            ->where('status', Voucher::STATUS_APPROVED)
            ->whereKeyNot($voucher->getKey())
            ->sum('amount');

        if ($alreadyFunded + (float) $voucher->amount > (float) $purchase->total_amount + 0.01) {
            throw ValidationException::withMessages([
                'amount' => 'Total funding for this purchase would exceed its purchase total.',
            ]);
        }
    }

    /**
     * Books the approved voucher into the existing accounting system and
     * returns the resulting downstream record (or null when the effect is
     * only a ledger entry / a fund-source balance contribution).
     */
    protected function applyAccountingEffect(Voucher $voucher): ?object
    {
        return match ($voucher->transaction_type) {
            'customer_payment' => $this->bookCustomerPayment($voucher),
            'supplier_payment' => $this->bookSupplierPayment($voucher),
            'business_expense' => $this->bookExpense($voucher),
            'inventory_purchase' => $this->bookFundSourceLedger($voucher, 'out'),
            'capital_investment', 'loan' => $this->bookFundSourceLedger($voucher, 'in'),
            'owner_withdrawal', 'refund', 'asset_purchase' => $this->bookFundSourceLedger($voucher, 'out'),
            'other' => $this->bookFundSourceLedger($voucher, $voucher->isCredit() ? 'in' : 'out'),
            default => null,
        };
    }

    protected function bookCustomerPayment(Voucher $voucher): CustomerPayment
    {
        return CustomerPayment::query()->create([
            'company_id' => $voucher->company_id,
            'customer_id' => $voucher->customer_id,
            'account_id' => $voucher->account_id,
            'amount' => $voucher->amount,
            'payment_date' => now()->toDateString(),
            'method' => $voucher->payment_method ?: 'other',
            'reference' => $voucher->voucher_number,
            'note' => $voucher->purpose,
        ]);
    }

    protected function bookSupplierPayment(Voucher $voucher): SupplierPayment
    {
        return SupplierPayment::query()->create([
            'company_id' => $voucher->company_id,
            'supplier_id' => $voucher->supplier_id,
            'account_id' => $voucher->account_id,
            'amount' => $voucher->amount,
            'payment_date' => now()->toDateString(),
            'method' => $voucher->payment_method ?: 'other',
            'reference' => $voucher->voucher_number,
            'note' => $voucher->purpose,
        ]);
    }

    protected function bookExpense(Voucher $voucher): Expense
    {
        return Expense::query()->create([
            'company_id' => $voucher->company_id,
            'expense_category_id' => $voucher->expense_category_id,
            'account_id' => $voucher->account_id,
            'amount' => $voucher->amount,
            'expense_date' => now()->toDateString(),
            'reference' => $voucher->voucher_number,
            'note' => $voucher->purpose,
        ]);
    }

    /**
     * For transaction types that never create an Expense/Payment record
     * (Rule 1): if the voucher's fund source is account-linked (cash/bank/
     * mobile/wallet/petty cash), book a ledger entry so the Account balance
     * moves. If the fund source is a capital-type pool (owner investment,
     * loan, etc. — no linked Account), there is nothing further to book: its
     * balance() already sums approved vouchers directly.
     */
    protected function bookFundSourceLedger(Voucher $voucher, string $direction): ?TransactionLedger
    {
        $accountId = $voucher->account_id;

        if (! $accountId && $voucher->fund_source_id) {
            $fundSource = FundSource::query()->find($voucher->fund_source_id);
            $accountId = $fundSource?->isAccountLinked() ? $fundSource->account_id : null;
        }

        if (! $accountId) {
            return null;
        }

        return TransactionLedger::query()->create([
            'company_id' => $voucher->company_id,
            'account_id' => $accountId,
            'type' => $voucher->isCredit() ? 'voucher_credit' : 'voucher_debit',
            'direction' => $direction,
            'amount' => $voucher->amount,
            'reference_type' => Voucher::class,
            'reference_id' => $voucher->getKey(),
            'transaction_date' => now()->toDateString(),
            'note' => "Voucher {$voucher->voucher_number}",
        ]);
    }
}
