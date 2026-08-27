<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            // Super-admin-only kill switch for the whole reseller module
            // (admin dashboard, storefront apply form, self-service store
            // management, and public /store/{slug} pages). Defaults off so
            // no existing company suddenly exposes reseller storefronts.
            $table->boolean('reseller_module_enabled')->default(false)->after('domain_verified');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('reseller_module_enabled');
        });
    }
};
