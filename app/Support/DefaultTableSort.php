<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * App-wide fallback default sort for Filament tables (owner request,
 * 2026-08-30: every list in the app should show its newest record first).
 *
 * Without an explicit ->defaultSort(), Filament's own CanSortRecords trait
 * still appends an ORDER BY on the primary key -- ascending -- as its final
 * tie-break (see vendor/filament/tables/src/Concerns/CanSortRecords.php),
 * which is exactly why lists were showing the oldest record on top and the
 * newest at the very bottom. Registered globally via Table::configureUsing()
 * in AppServiceProvider so every Filament table (resource tables, relation
 * manager tables, widget tables) gets this without touching each one.
 *
 * A resource/relation manager that calls its own ->defaultSort() afterwards
 * simply overwrites this — that call always runs later in the chain (see
 * Table::make() vs HasTable::makeTable()) — so nothing here overrides an
 * intentional, already-correct sort (alphabetical, drag-reorder, etc.).
 */
class DefaultTableSort
{
    /**
     * Per-table-name cache of whether a created_at column exists, so a page
     * with several tables (main table + relation managers) doesn't repeat
     * the same schema lookup for the same table.
     *
     * @var array<string, bool>
     */
    protected static array $hasCreatedAtColumn = [];

    /**
     * Resolves the column to sort by for one query at render time (not at
     * table-construction time, when no query/model is known yet) -- pass
     * this straight to Table::defaultSort() as a Closure.
     */
    public static function column(Builder $query): string
    {
        $model = $query->getModel();
        $table = $model->getTable();
        $column = $model->getCreatedAtColumn() ?: 'created_at';

        self::$hasCreatedAtColumn[$table] ??= Schema::hasColumn($table, $column);

        return self::$hasCreatedAtColumn[$table] ? $column : $model->getKeyName();
    }
}
