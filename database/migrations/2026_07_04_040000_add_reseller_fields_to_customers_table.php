<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('reseller_status')->default('none')->after('customer_source');
            $table->string('business_name')->nullable()->after('reseller_status');
            $table->text('reseller_note')->nullable()->after('business_name');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn(['reseller_status', 'business_name', 'reseller_note']);
        });
    }
};
