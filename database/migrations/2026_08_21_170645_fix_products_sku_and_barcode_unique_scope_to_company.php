<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Same bug/fix as 2026_08_21_163643_fix_categories_slug_unique_scope_to_company.php,
 * applied to `products`:
 *
 * - `sku` had a database-wide unique() constraint from before multi-company
 *   support existed, even though Product is company-scoped - two companies
 *   independently importing/entering a product with the same SKU (a very
 *   real scenario for WooCommerce imports from two different stores, or
 *   simply two companies stocking the same manufacturer's item) crashes
 *   instead of getting its own company-scoped row. Replaced with a unique
 *   index on (company_id, sku).
 * - `barcode` never had a database-level unique constraint at all - only the
 *   admin form (ProductForm/PurchaseForm) enforced "unique" via Filament
 *   validation, unscoped by company, so two companies could never save the
 *   same barcode even though nothing in the database actually required that.
 *   Added a proper (company_id, barcode) unique index so the database now
 *   backs what the form already promises, scoped correctly per company.
 *
 * Every step is guarded by the table's actual current indexes rather than
 * assumed by name - see the docblock on the sibling categories migration for
 * why: a production run found `products_sku_unique`/`products_company_lookup_index`
 * already absent, which made an unconditional `dropUnique()` fail the deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        $indexes = collect(Schema::getIndexes('products'))->pluck('name')->all();

        Schema::table('products', function (Blueprint $table) use ($indexes) {
            if (in_array('products_sku_unique', $indexes, true)) {
                $table->dropUnique('products_sku_unique');
            }

            if (in_array('products_company_lookup_index', $indexes, true)) {
                $table->dropIndex('products_company_lookup_index');
            }

            if (! in_array('products_company_id_sku_unique', $indexes, true)) {
                $table->unique(['company_id', 'sku'], 'products_company_id_sku_unique');
            }

            if (! in_array('products_company_id_barcode_unique', $indexes, true)) {
                $table->unique(['company_id', 'barcode'], 'products_company_id_barcode_unique');
            }
        });
    }

    public function down(): void
    {
        $indexes = collect(Schema::getIndexes('products'))->pluck('name')->all();

        Schema::table('products', function (Blueprint $table) use ($indexes) {
            if (in_array('products_company_id_barcode_unique', $indexes, true)) {
                $table->dropUnique('products_company_id_barcode_unique');
            }

            if (in_array('products_company_id_sku_unique', $indexes, true)) {
                $table->dropUnique('products_company_id_sku_unique');
            }

            if (! in_array('products_company_lookup_index', $indexes, true)) {
                $table->index(['company_id', 'sku'], 'products_company_lookup_index');
            }

            if (! in_array('products_sku_unique', $indexes, true)) {
                $table->unique('sku', 'products_sku_unique');
            }
        });
    }
};
