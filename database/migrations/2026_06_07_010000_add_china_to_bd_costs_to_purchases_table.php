<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            foreach ($this->costColumns() as $column) {
                if (! Schema::hasColumn('purchases', $column)) {
                    $table->decimal($column, 12, 2)->default(0)->after('vat');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $columns = array_filter(
                $this->costColumns(),
                fn (string $column): bool => Schema::hasColumn('purchases', $column),
            );

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    protected function costColumns(): array
    {
        return [
            'machine_purchase',
            'inspection',
            'freight_to_ctg',
            'duty',
            'c_and_f',
            'misc',
            'truck',
            'load_unload',
            'spare_parts',
            'cam',
            'positive_feeder',
            'cylinder',
        ];
    }
};
