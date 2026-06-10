<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Expense extends Model
{
    protected $fillable = [
        'expense_number',
        'expense_category_id',
        'account_id',
        'amount',
        'expense_date',
        'reference',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (Expense $expense): void {
            $expense->expense_number ??= static::nextExpenseNumber();
            $expense->expense_date ??= now()->toDateString();
        });

        static::saving(function (Expense $expense): void {
            if ((float) $expense->amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Expense amount must be greater than zero.',
                ]);
            }

            $ledger = TransactionLedger::query()
                ->where('reference_type', self::class)
                ->where('reference_id', $expense->getKey())
                ->first();

            if ($expense->account?->projectedBalanceAfterOutflow((float) $expense->amount, $ledger?->id) < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'This expense would make the account balance negative.',
                ]);
            }
        });

        static::saved(function (Expense $expense): void {
            $expense->syncLedger();
        });

        static::deleted(function (Expense $expense): void {
            $expense->deleteLedger();
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public static function nextExpenseNumber(): string
    {
        return 'EXP-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5));
    }

    public function syncLedger(): void
    {
        TransactionLedger::query()->updateOrCreate(
            [
                'reference_type' => self::class,
                'reference_id' => $this->getKey(),
            ],
            [
                'account_id' => $this->account_id,
                'type' => 'expense',
                'direction' => 'out',
                'amount' => $this->amount,
                'transaction_date' => $this->expense_date,
                'note' => "Expense {$this->expense_number}",
            ],
        );
    }

    protected function deleteLedger(): void
    {
        TransactionLedger::query()
            ->where('reference_type', self::class)
            ->where('reference_id', $this->getKey())
            ->get()
            ->each
            ->delete();
    }
}
