<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->string('contact_email')->nullable()->after('phone_number');
            $table->string('contact_hours')->nullable()->after('contact_email');
        });
    }

    public function down(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropColumn(['contact_email', 'contact_hours']);
        });
    }
};
