<?php

namespace App\Support;

use App\Services\CompanyContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

/**
 * Filament's `TextInput::unique()` validates directly against the database
 * table and never applies a model's Eloquent global scopes (see
 * Filament\Forms\Components\Concerns\CanBeValidated::unique() -
 * it always builds a plain `Rule::unique($table, $column)`), so a
 * company-scoped column (whose actual DB uniqueness is `[company_id, column]`,
 * not `column` alone) still gets rejected in the admin form as "already
 * taken" the moment another company has the same value.
 *
 * Pass this as `modifyRuleUsing` on any such field to scope the check to the
 * record's own company: the record being edited if there is one, otherwise
 * the currently selected company from CompanyContext.
 */
class CompanyScopedUnique
{
    public static function rule(): callable
    {
        return static function (Unique $rule, ?Model $record): Unique {
            $companyId = $record?->company_id ?? app(CompanyContext::class)->id();

            return $rule->where('company_id', $companyId);
        };
    }
}
