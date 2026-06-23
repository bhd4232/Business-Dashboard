<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courier_bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('courier_bookings', 'provider_reference')) {
                $table->string('provider_reference')->nullable()->after('tracking_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('courier_bookings', function (Blueprint $table): void {
            if (Schema::hasColumn('courier_bookings', 'provider_reference')) {
                $table->dropColumn('provider_reference');
            }
        });
    }
};
