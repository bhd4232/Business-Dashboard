<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The company's wholesale/cost-basis rate for this reseller on this
     * specific product -- set by admin/staff (App\Filament\Resources\
     * Resellers\RelationManagers\ProductsRelationManager), never by the
     * reseller themselves. This round's commission calculation uses the
     * order's existing sale price (public/tier price -- reseller-set retail
     * pricing is a deferred follow-up, see 10_INVESTOR_RESELLER_AUTO_PAYOUT_PLAN.md)
     * minus this rate.
     */
    public function up(): void
    {
        Schema::table('reseller_products', function (Blueprint $table): void {
            $table->decimal('wholesale_rate', 15, 2)->nullable()->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('reseller_products', function (Blueprint $table): void {
            $table->dropColumn('wholesale_rate');
        });
    }
};
