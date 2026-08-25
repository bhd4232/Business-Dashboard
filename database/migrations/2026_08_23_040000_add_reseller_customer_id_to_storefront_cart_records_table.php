<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_cart_records', function (Blueprint $table): void {
            // Remembers which reseller's store this cart was started under,
            // so checkout can still attribute the resulting Order correctly
            // even after redirects between requests.
            $table->foreignId('reseller_customer_id')
                ->nullable()
                ->after('company_id')
                ->constrained('customers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('storefront_cart_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reseller_customer_id');
        });
    }
};
