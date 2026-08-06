<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('login_otp_code')->nullable()->after('password_reset_expires_at');
            $table->string('login_otp_channel', 10)->nullable()->after('login_otp_code');
            $table->timestamp('login_otp_expires_at')->nullable()->after('login_otp_channel');
            $table->timestamp('login_otp_sent_at')->nullable()->after('login_otp_expires_at');
            $table->unsignedTinyInteger('login_otp_attempts')->default(0)->after('login_otp_sent_at');
        });

        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->text('inside_dhaka_keywords')->nullable()->after('delivery_additional_per_kg');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn([
                'login_otp_code',
                'login_otp_channel',
                'login_otp_expires_at',
                'login_otp_sent_at',
                'login_otp_attempts',
            ]);
        });

        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropColumn('inside_dhaka_keywords');
        });
    }
};
