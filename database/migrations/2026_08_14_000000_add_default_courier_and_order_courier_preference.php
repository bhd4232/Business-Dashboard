<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courier_providers', function (Blueprint $table): void {
            $table->boolean('is_default')->default(false)->after('is_active');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('courier_provider_id')->nullable()->after('delivery_status')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('courier_provider_id');
        });

        Schema::table('courier_providers', function (Blueprint $table): void {
            $table->dropColumn('is_default');
        });
    }
};
