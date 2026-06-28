<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('companies', 'domain')) {
                $table->string('domain')->nullable()->unique()->after('slug');
            }

            if (! Schema::hasColumn('companies', 'domain_verified')) {
                $table->boolean('domain_verified')->default(false)->after('domain');
            }
        });

        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'slug')) {
                $table->string('slug')->nullable()->after('name');
                $table->index(['company_id', 'slug'], 'products_company_slug_index');
            }
        });

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'source')) {
                $table->string('source')->default('admin')->after('delivery_status');
                $table->index(['company_id', 'source'], 'orders_company_source_index');
            }
        });

        $this->backfillProductSlugs();
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'source')) {
                $table->dropIndex('orders_company_source_index');
                $table->dropColumn('source');
            }
        });

        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'slug')) {
                $table->dropIndex('products_company_slug_index');
                $table->dropColumn('slug');
            }
        });

        Schema::table('companies', function (Blueprint $table): void {
            if (Schema::hasColumn('companies', 'domain')) {
                $table->dropUnique(['domain']);
            }

            if (Schema::hasColumn('companies', 'domain_verified')) {
                $table->dropColumn('domain_verified');
            }

            if (Schema::hasColumn('companies', 'domain')) {
                $table->dropColumn('domain');
            }
        });
    }

    protected function backfillProductSlugs(): void
    {
        if (! Schema::hasColumn('products', 'slug')) {
            return;
        }

        DB::table('products')
            ->select(['id', 'company_id', 'name', 'sku'])
            ->orderBy('id')
            ->get()
            ->each(function ($product): void {
                $base = Str::slug((string) $product->name) ?: Str::slug((string) $product->sku) ?: 'product-'.$product->id;
                $slug = $base;
                $suffix = 2;

                while (
                    DB::table('products')
                        ->where('company_id', $product->company_id)
                        ->where('slug', $slug)
                        ->where('id', '!=', $product->id)
                        ->exists()
                ) {
                    $slug = "{$base}-{$suffix}";
                    $suffix++;
                }

                DB::table('products')->where('id', $product->id)->update(['slug' => $slug]);
            });
    }
};
