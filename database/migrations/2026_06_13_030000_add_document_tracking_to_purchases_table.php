<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            foreach (['lc_number', 'pi_number', 'ci_number'] as $column) {
                if (! Schema::hasColumn('purchases', $column)) {
                    $table->string($column)->nullable()->after('purchase_date');
                }
            }

            foreach (['lc_date', 'pi_date', 'ci_date'] as $column) {
                if (! Schema::hasColumn('purchases', $column)) {
                    $table->date($column)->nullable()->after(str_replace('_date', '_number', $column));
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            foreach (['lc_date', 'lc_number', 'pi_date', 'pi_number', 'ci_date', 'ci_number'] as $column) {
                if (Schema::hasColumn('purchases', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
