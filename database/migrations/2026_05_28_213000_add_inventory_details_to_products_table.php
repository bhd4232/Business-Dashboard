<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'description')) {
                $table->text('description')->nullable()->after('name');
            }

            if (! Schema::hasColumn('products', 'barcode')) {
                $table->string('barcode')->nullable()->after('sku');
            }

            if (! Schema::hasColumn('products', 'unit')) {
                $table->string('unit', 50)->default('pcs')->after('barcode');
            }

            if (! Schema::hasColumn('products', 'brand')) {
                $table->string('brand')->nullable()->after('unit');
            }

            if (! Schema::hasColumn('products', 'cost_price')) {
                $table->decimal('cost_price', 12, 2)->default(0)->after('brand');
            }

            if (! Schema::hasColumn('products', 'sale_price')) {
                $table->decimal('sale_price', 12, 2)->nullable()->after('cost_price');
            }

            if (! Schema::hasColumn('products', 'reorder_level')) {
                $table->integer('reorder_level')->default(0)->after('stock');
            }

            if (! Schema::hasColumn('products', 'vat_rate')) {
                $table->decimal('vat_rate', 5, 2)->default(0)->after('reorder_level');
            }

            if (! Schema::hasColumn('products', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('vat_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach ([
                'description',
                'barcode',
                'unit',
                'brand',
                'cost_price',
                'sale_price',
                'reorder_level',
                'vat_rate',
                'is_active',
            ] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
