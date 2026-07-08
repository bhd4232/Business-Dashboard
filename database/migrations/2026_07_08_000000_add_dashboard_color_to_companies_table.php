<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            // Intentionally separate from any storefront branding color
            // (StorefrontSetting::theme_color) — the dashboard color is
            // chosen for admin-panel readability/accessibility per company,
            // not customer-facing branding.
            $table->string('dashboard_color', 7)->default('#F59E0B')->after('settings');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('dashboard_color');
        });
    }
};
