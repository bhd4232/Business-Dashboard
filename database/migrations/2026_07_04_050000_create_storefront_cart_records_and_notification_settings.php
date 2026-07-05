<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_cart_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('session_id')->index();
            $table->string('phone')->nullable();
            $table->string('customer_name')->nullable();
            $table->json('items');
            $table->string('status')->default('active');
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'session_id']);
        });

        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->boolean('abandoned_cart_reminders_enabled')->default(false)->after('payment_credentials');
            $table->unsignedSmallInteger('abandoned_cart_delay_hours')->default(6)->after('abandoned_cart_reminders_enabled');
            $table->text('notification_credentials')->nullable()->after('abandoned_cart_delay_hours');
        });
    }

    public function down(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropColumn(['abandoned_cart_reminders_enabled', 'abandoned_cart_delay_hours', 'notification_credentials']);
        });

        Schema::dropIfExists('storefront_cart_records');
    }
};
