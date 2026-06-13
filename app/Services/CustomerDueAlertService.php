<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CustomerDueAlertService
{
    public function query(): Builder
    {
        return Customer::query()
            ->where('current_balance', '>', 0);
    }

    public function count(): int
    {
        return (int) $this->query()->count();
    }

    public function customers(int $limit = 10): Collection
    {
        return $this->query()
            ->orderByDesc('current_balance')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    public function totalDue(): float
    {
        return (float) $this->query()->sum('current_balance');
    }

    public function hasAlerts(): bool
    {
        return $this->count() > 0;
    }

    public function message(): string
    {
        $count = $this->count();
        $total = number_format($this->totalDue(), 2);

        return $count === 1
            ? "1 customer has BDT {$total} due."
            : "{$count} customers have BDT {$total} due.";
    }
}
