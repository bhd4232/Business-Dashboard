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
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_sku_unique');
            $table->dropIndex('products_company_lookup_index');
            $table->unique(['company_id', 'sku'], 'products_company_id_sku_unique');
            $table->unique(['company_id', 'barcode'], 'products_company_id_barcode_unique');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_company_id_barcode_unique');
            $table->dropUnique('products_company_id_sku_unique');
            $table->index(['company_id', 'sku'], 'products_company_lookup_index');
            $table->unique('sku', 'products_sku_unique');
        });
    }
};
