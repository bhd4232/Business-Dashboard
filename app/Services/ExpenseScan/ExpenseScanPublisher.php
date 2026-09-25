<?php

namespace App\Services\ExpenseScan;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseScan;
use App\Models\ExpenseScanItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a reviewed ExpenseScan's draft lines into real Expenses (each posts
 * to the ledger through Expense's own saved hook). All-or-nothing: one bad
 * line — missing date, amount, category or account, or an account that
 * would go negative — publishes nothing and reports which line to fix.
 */
class ExpenseScanPublisher
{
    public function publish(ExpenseScan $scan, User $user): int
    {
        return DB::transaction(function () use ($scan, $user): int {
            $scan = ExpenseScan::query()->lockForUpdate()->findOrFail($scan->getKey());

            if ($scan->isPublished()) {
                throw ValidationException::withMessages(['items' => 'This scan has already been published.']);
            }

            $items = $scan->items()->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'There are no expense lines to publish.']);
            }

            $this->validate($items->all());

            $newCategories = [];

            foreach ($items->values() as $index => $item) {
                $categoryId = $item->expense_category_id
                    ?? $this->categoryIdFor((string) $item->new_category_name, $newCategories);

                try {
                    $expense = Expense::query()->create([
                        'expense_category_id' => $categoryId,
                        'account_id' => $item->account_id,
                        'amount' => $item->amount,
                        'expense_date' => $item->expense_date,
                        'reference' => $item->reference,
                        'note' => collect([$item->description, $item->note])->filter(fn ($text): bool => filled($text))->implode("\n") ?: null,
                        'expense_scan_id' => $scan->getKey(),
                    ]);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages([
                        'items' => 'Line '.($index + 1).': '.collect($exception->errors())->flatten()->first(),
                    ]);
                }

                $item->forceFill(['expense_id' => $expense->getKey(), 'expense_category_id' => $categoryId])->save();
            }

            $scan->forceFill([
                'status' => ExpenseScan::STATUS_PUBLISHED,
                'published_at' => now(),
                'published_by' => $user->getKey(),
            ])->save();

            return $items->count();
        });
    }

    /**
     * An existing Expense with the same date and amount — shown to the
     * reviewer as a possible duplicate (a warning, never a block).
     */
    public function possibleDuplicate(?string $date, mixed $amount, ?int $excludingScanId = null): ?Expense
    {
        if (blank($date) || ! is_numeric($amount) || (float) $amount <= 0) {
            return null;
        }

        return Expense::query()
            ->whereDate('expense_date', $date)
            ->where('amount', round((float) $amount, 2))
            ->when($excludingScanId, fn ($query) => $query->where(fn ($query) => $query
                ->whereNull('expense_scan_id')
                ->orWhere('expense_scan_id', '!=', $excludingScanId)))
            ->first();
    }

    /** @param  array<int, ExpenseScanItem>  $items */
    protected function validate(array $items): void
    {
        $problems = [];

        foreach (array_values($items) as $index => $item) {
            $missing = [];

            if (! $item->expense_date) {
                $missing[] = 'date';
            }

            if ((float) $item->amount <= 0) {
                $missing[] = 'amount';
            }

            if (! $item->expense_category_id && blank($item->new_category_name)) {
                $missing[] = 'category';
            }

            if (! $item->account_id) {
                $missing[] = 'pay-from account';
            }

            if ($missing !== []) {
                $problems[] = 'Line '.($index + 1).' is missing: '.implode(', ', $missing).'.';
            }
        }

        if ($problems !== []) {
            throw ValidationException::withMessages(['items' => $problems]);
        }
    }

    /**
     * Reuse an active category with the same name (case-insensitive) —
     * including one created earlier in this same publish — before creating.
     *
     * @param  array<string, int>  $created
     */
    protected function categoryIdFor(string $name, array &$created): int
    {
        $name = trim($name);
        $key = mb_strtolower($name);

        if (isset($created[$key])) {
            return $created[$key];
        }

        $existing = ExpenseCategory::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(name) = ?', [$key])
            ->value('id');

        return $created[$key] = (int) ($existing ?: ExpenseCategory::createWithUniqueSlug($name)->getKey());
    }
}
