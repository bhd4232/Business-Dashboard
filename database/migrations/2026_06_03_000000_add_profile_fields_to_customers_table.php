<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'customer_type')) {
                $table->string('customer_type', 50)->default('regular')->after('address');
            }

            if (! Schema::hasColumn('customers', 'customer_source')) {
                $table->string('customer_source', 50)->nullable()->after('customer_type');
            }

            $table->index(['customer_type', 'customer_source'], 'customers_type_source_index');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_type_source_index');

            foreach (['customer_source', 'customer_type'] as $column) {
                if (Schema::hasColumn('customers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
