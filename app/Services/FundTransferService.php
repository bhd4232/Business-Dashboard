<?php

namespace App\Services;

use App\Models\FundTransfer;
use App\Models\TransactionLedger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FundTransferService
{
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
