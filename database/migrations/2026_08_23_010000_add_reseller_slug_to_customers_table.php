<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            // The URL segment for an approved reseller's public storefront
            // ({company-domain}/store/{reseller_slug}). Only has to be
            // unique within a company, not globally -- same scoping as
            // Product/Category slugs already use.
            $table->string('reseller_slug')->nullable()->after('reseller_note');
            $table->unique(['company_id', 'reseller_slug']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'reseller_slug']);
            $table->dropColumn('reseller_slug');
        });
    }
};
