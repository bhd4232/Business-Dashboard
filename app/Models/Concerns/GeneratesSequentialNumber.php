<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;

/**
 * Makes a human-readable sequential document number (e.g. an order or
 * purchase number) safe under concurrency.
 *
 * The number is generated in the model's `creating` event from the current
 * max, which has an inherent read-then-insert race: two requests can read the
 * same max and mint the same number, then collide on the column's UNIQUE
 * index. This concern retries the INSERT a few times, clearing the number so
 * the `creating` hook regenerates it from the now-committed max, until it
 * lands a free value. It is database-agnostic (relies on the UNIQUE index, not
 * on row locking), so it holds on both SQLite and MySQL/Postgres.
 */
trait GeneratesSequentialNumber
{
    /** Column that carries the UNIQUE sequential number. */
    abstract protected function sequentialNumberColumn(): string;

    protected function performInsert(Builder $query)
    {
        $column = $this->sequentialNumberColumn();
        $maxAttempts = 5;

        for ($attempt = 1; ; $attempt++) {
            try {
                return parent::performInsert($query);
            } catch (QueryException $exception) {
                if ($attempt >= $maxAttempts || ! $this->isDuplicateSequentialNumber($exception, $column)) {
                    throw $exception;
                }

                // Force the `creating` hook to mint a fresh number from the
                // now-advanced max on the next attempt.
                $this->{$column} = null;
                usleep(random_int(1_000, 5_000));
            }
        }
    }

    protected function isDuplicateSequentialNumber(QueryException $exception, string $column): bool
    {
        if ((string) $exception->getCode() !== '23000') {
            return false;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique') && str_contains($message, $column);
    }
}
