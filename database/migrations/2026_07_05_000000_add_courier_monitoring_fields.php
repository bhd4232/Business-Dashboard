<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courier_bookings', function (Blueprint $table): void {
            $table->timestamp('last_synced_at')->nullable()->after('booked_at');
        });

        Schema::table('courier_providers', function (Blueprint $table): void {
            $table->unsignedInteger('sync_failure_count')->default(0)->after('is_active');
            $table->text('last_sync_error')->nullable()->after('sync_failure_count');
            $table->timestamp('last_synced_at')->nullable()->after('last_sync_error');
        });
    }

    public function down(): void
    {
        Schema::table('courier_bookings', function (Blueprint $table): void {
            $table->dropColumn('last_synced_at');
        });

        Schema::table('courier_providers', function (Blueprint $table): void {
            $table->dropColumn(['sync_failure_count', 'last_sync_error', 'last_synced_at']);
        });
    }
};
