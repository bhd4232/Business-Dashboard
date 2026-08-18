<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Company;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CompanyDeletionService
{
    public function delete(Company $company): bool
    {
        try {
            return DB::transaction(function () use ($company): bool {
                if (Schema::hasTable('audit_logs')) {
                    DB::table('audit_logs')
                        ->where('company_id', $company->getKey())
                        ->delete();
                }

                if (Schema::hasTable('accounts') && Schema::hasColumn('accounts', 'system_key')) {
                    Account::withoutGlobalScopes()
                        ->where('company_id', $company->getKey())
                        ->whereNotNull('system_key')
                        ->delete();
                }

                return (bool) $company->delete();
            });
        } catch (QueryException) {
            return false;
        }
    }
}
