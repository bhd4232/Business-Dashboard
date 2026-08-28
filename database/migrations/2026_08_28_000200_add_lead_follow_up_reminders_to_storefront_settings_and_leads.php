<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table) {
            $table->boolean('lead_follow_up_reminders_enabled')->default(false)->after('abandoned_cart_delay_hours');
        });

        Schema::table('leads', function (Blueprint $table) {
            // Cleared whenever next_follow_up_at changes (see Lead::booted()),
            // so moving the follow-up date re-arms the reminder instead of
            // silently staying "already reminded" against the old date.
            $table->dateTime('follow_up_reminded_at')->nullable()->after('next_follow_up_at');
        });
    }

    public function down(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table) {
            $table->dropColumn('lead_follow_up_reminders_enabled');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('follow_up_reminded_at');
        });
    }
};
