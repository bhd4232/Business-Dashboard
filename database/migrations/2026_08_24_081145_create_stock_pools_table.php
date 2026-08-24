<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deliberately not company-owned: a pool exists precisely to span two
        // (or more) companies' Product rows that share one physical stock —
        // see App\Models\StockPool for the full rationale. Excluded from
        // MultiCompanyIsolationTest on purpose, same as CustomerBlacklist.
        Schema::create('stock_pools', function (Blueprint $table): void {
            $table->id();
            // The member product treated as the physical owner of the real
            // stock (holds opening/purchase movements); every other member
            // product's own ledger nets to zero and only mirrors it. Nullable
            // FK, set right after creation — see StockPoolResource.
            $table->foreignId('source_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('stock_pool_id')->nullable()->after('company_id')->constrained('stock_pools')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('stock_pool_id');
        });

        Schema::dropIfExists('stock_pools');
    }
};
