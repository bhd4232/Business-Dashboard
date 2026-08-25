<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `categories.slug` was created with a database-wide unique constraint
 * (2026_05_26_140736_create_categories_table.php) before company scoping
 * existed. Once `company_id` was added, the app logic (Category model's
 * BelongsToCompany/CompanyScope, WooCommerceImportService::resolveCategory())
 * only ever checks for an existing slug within the current company - but the
 * database still rejects any second company reusing a slug another company
 * already has (e.g. every company independently getting an "Audio" category
 * from WooCommerce import), causing a 500 on save/sync.
 *
 * This replaces the global unique constraint with a per-company one, matching
 * every other company-scoped table's uniqueness rule in this codebase.
 *
 * Every step is guarded by the table's actual current indexes rather than
 * assumed by name: a production run surfaced a `categories` table whose live
 * schema had already drifted from what a fresh `create_categories_table` run
 * would produce (no `categories_slug_unique`/`categories_company_lookup_index`
 * present), which made the original unconditional `dropUnique()` fail the
 * whole deploy with "Can't DROP ...; check that column/key exists". Checking
 * `Schema::getIndexes()` first makes this migration correct regardless of
 * which of these indexes actually exist, and safely re-runnable.
 *
 * Ordering also matters: `categories.company_id` carries a foreign key to
 * `companies`, and MySQL refuses to drop an index while it is the FK's only
 * remaining supporting index (error 1553). `categories_company_lookup_index`
 * is exactly that supporting index today, so the replacement
 * `categories_company_id_slug_unique` (which also starts with `company_id`)
 * must be created FIRST, in its own `Schema::table()` call, before the old
 * indexes are dropped — otherwise the FK is briefly left uncovered and MySQL
 * rejects the drop.
 */
return new class extends Migration
{
    public function up(): void
    {
        $indexes = collect(Schema::getIndexes('categories'))->pluck('name')->all();

        if (! in_array('categories_company_id_slug_unique', $indexes, true)) {
            Schema::table('categories', function (Blueprint $table) {
                $table->unique(['company_id', 'slug'], 'categories_company_id_slug_unique');
            });
        }

        $indexes = collect(Schema::getIndexes('categories'))->pluck('name')->all();

        Schema::table('categories', function (Blueprint $table) use ($indexes) {
            if (in_array('categories_slug_unique', $indexes, true)) {
                $table->dropUnique('categories_slug_unique');
            }

            if (in_array('categories_company_lookup_index', $indexes, true)) {
                $table->dropIndex('categories_company_lookup_index');
            }
        });
    }

    public function down(): void
    {
        $indexes = collect(Schema::getIndexes('categories'))->pluck('name')->all();

        if (! in_array('categories_company_lookup_index', $indexes, true)) {
            Schema::table('categories', function (Blueprint $table) {
                $table->index(['company_id', 'slug'], 'categories_company_lookup_index');
            });
        }

        $indexes = collect(Schema::getIndexes('categories'))->pluck('name')->all();

        Schema::table('categories', function (Blueprint $table) use ($indexes) {
            if (in_array('categories_company_id_slug_unique', $indexes, true)) {
                $table->dropUnique('categories_company_id_slug_unique');
            }

            if (! in_array('categories_slug_unique', $indexes, true)) {
                $table->unique('slug', 'categories_slug_unique');
            }
        });
    }
};
