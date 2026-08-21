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
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_slug_unique');
            $table->dropIndex('categories_company_lookup_index');
            $table->unique(['company_id', 'slug'], 'categories_company_id_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_company_id_slug_unique');
            $table->index(['company_id', 'slug'], 'categories_company_lookup_index');
            $table->unique('slug', 'categories_slug_unique');
        });
    }
};
