<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('courier_providers') || ! Schema::hasColumn('courier_providers', 'credentials')) {
            return;
        }

        Schema::table('courier_providers', function (Blueprint $table): void {
            $table->text('credentials')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Intentionally irreversible: CourierProvider uses an encrypted:array
        // cast, whose ciphertext is not valid for a database JSON column.
    }
};
