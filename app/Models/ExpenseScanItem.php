<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One draft expense line read by the AI from an ExpenseScan photo. Every
 * field is editable by the reviewer; `expense_id` is set once the line has
 * been published into a real Expense.
 */
class ExpenseScanItem extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'expense_scan_id',
        'expense_date',
        'description',
        'amount',
        'expense_category_id',
        'new_category_name',
        'account_id',
        'reference',
        'note',
        'confidence',
        'sort_order',
        'expense_id',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
        'confidence' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (ExpenseScanItem $item): void {
            $item->company_id ??= $item->scan?->company_id;
        });
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(ExpenseScan::class, 'expense_scan_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
