<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // Deliberately separate from the existing `customer_id` (the
            // buyer): this records which reseller's storefront the order
            // was placed through, for future commission/payout work. A
            // reseller could even buy from their own store, so these two
            // columns are never the same relationship.
            $table->foreignId('reseller_customer_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('customers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reseller_customer_id');
        });
    }
};
