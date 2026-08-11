<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->boolean('meta_status_events_enabled')->default(false)->after('meta_capi_enabled');
            $table->json('meta_status_events')->nullable()->after('meta_status_events_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropColumn(['meta_status_events_enabled', 'meta_status_events']);
        });
    }
};
