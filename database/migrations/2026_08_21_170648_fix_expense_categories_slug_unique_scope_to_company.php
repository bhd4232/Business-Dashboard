<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Same bug/fix as 2026_08_21_163643_fix_categories_slug_unique_scope_to_company.php,
 * applied to `expense_categories`: `slug` had a database-wide unique()
 * constraint from before multi-company support existed, even though
 * ExpenseCategory is company-scoped. Expense category names are especially
 * likely to collide across companies (e.g. every company independently
 * having a "Rent" or "Utilities" category), which crashed the insert
 * instead of getting its own company-scoped row. Replaced with a unique
 * index on (company_id, slug).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropUnique('expense_categories_slug_unique');
            $table->unique(['company_id', 'slug'], 'expense_categories_company_id_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropUnique('expense_categories_company_id_slug_unique');
            $table->unique('slug', 'expense_categories_slug_unique');
        });
    }
};
