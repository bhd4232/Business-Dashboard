<?php

namespace App\Services;

use App\Models\FundTransfer;
use App\Models\TransactionLedger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FundTransferService
{
    public function submit(array $data, User $user): FundTransfer
    {
        if (! $user->canCreateFundTransfer()) {
            throw ValidationException::withMessages([
                'type' => 'You do not have permission to create a fund transfer.',
            ]);
        }

        if ((float) ($data['amount'] ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be greater than zero.',
            ]);
        }

        if ((float) ($data['transaction_cost'] ?? 0) < 0) {
            throw ValidationException::withMessages([
                'transaction_cost' => 'Transaction cost cannot be negative.',
            ]);
        }

        if (empty($data['from_account_id']) || empty($data['to_account_id'])) {
            throw ValidationException::withMessages([
                'from_account_id' => 'Both source and destination accounts are required.',
            ]);
        }

        if ((int) $data['from_account_id'] === (int) $data['to_account_id']) {
            throw ValidationException::withMessages([
                'to_account_id' => 'The destination account must be different from the source account.',
            ]);
        }

        return FundTransfer::query()->create([
            'from_account_id' => $data['from_account_id'],
            'to_account_id' => $data['to_account_id'],
            'amount' => $data['amount'],
            'transaction_cost' => $data['transaction_cost'] ?? 0,
            'remarks' => $data['remarks'] ?? null,
            'requested_by' => $user->getKey(),
        ]);
    }

    public function approve(FundTransfer $transfer, User $user): void
    {
        if ($transfer->status !== FundTransfer::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Only a pending transfer can be approved.',
            ]);
        }

        if ($transfer->from_account_id === $transfer->to_account_id) {
            throw ValidationException::withMessages([
                'to_account_id' => 'The destination account must be different from the source account.',
            ]);
        }

        DB::transaction(function () use ($transfer, $user): void {
            TransactionLedger::query()->create([
                'company_id' => $transfer->company_id,
                'account_id' => $transfer->from_account_id,
                'type' => 'fund_transfer',
                'direction' => 'out',
                'amount' => $transfer->amount,
                'reference_type' => FundTransfer::class,
                'reference_id' => $transfer->getKey(),
                'transaction_date' => now()->toDateString(),
                'note' => "Fund transfer {$transfer->transfer_number} to account #{$transfer->to_account_id}",
            ]);

            TransactionLedger::query()->create([
                'company_id' => $transfer->company_id,
                'account_id' => $transfer->to_account_id,
                'type' => 'fund_transfer',
                'direction' => 'in',
                'amount' => $transfer->amount,
                'reference_type' => FundTransfer::class,
                'reference_id' => $transfer->getKey(),
                'transaction_date' => now()->toDateString(),
                'note' => "Fund transfer {$transfer->transfer_number} from account #{$transfer->from_account_id}",
            ]);

            if ((float) $transfer->transaction_cost > 0) {
                TransactionLedger::query()->create([
                    'company_id' => $transfer->company_id,
                    'account_id' => $transfer->from_account_id,
                    'type' => 'fund_transfer_cost',
                    'direction' => 'out',
                    'amount' => $transfer->transaction_cost,
                    'reference_type' => FundTransfer::class,
                    'reference_id' => $transfer->getKey(),
                    'transaction_date' => now()->toDateString(),
                    'note' => "Transaction cost for fund transfer {$transfer->transfer_number}",
                ]);
            }

            $transfer->update([
                'status' => FundTransfer::STATUS_APPROVED,
                'approved_by' => $user->getKey(),
                'approved_at' => now(),
            ]);
        });
    }

    public function reject(FundTransfer $transfer, User $user): void
    {
        if ($transfer->status !== FundTransfer::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Only a pending transfer can be rejected.',
            ]);
        }

        $transfer->update([
            'status' => FundTransfer::STATUS_REJECTED,
            'approved_by' => $user->getKey(),
            'approved_at' => now(),
        ]);
    }
}
