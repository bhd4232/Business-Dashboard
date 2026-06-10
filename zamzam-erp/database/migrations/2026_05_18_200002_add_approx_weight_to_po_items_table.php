<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('po_items', function (Blueprint $table) {
            $table->decimal('approx_weight_kg', 10, 3)->nullable()->default(null)->after('quantity')
                  ->comment('Approximate weight per unit in kg, for shipping cost estimation');
        });
    }

    public function down(): void
    {
        Schema::table('po_items', function (Blueprint $table) {
            $table->dropColumn('approx_weight_kg');
        });
    }
};
