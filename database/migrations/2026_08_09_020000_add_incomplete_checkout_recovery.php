<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_cart_records', function (Blueprint $table): void {
            $table->text('email')->nullable()->after('customer_name');
            $table->text('address')->nullable()->after('email');
            $table->decimal('subtotal', 14, 2)->default(0)->after('items');
            $table->timestamp('checkout_started_at')->nullable()->after('status');
            $table->timestamp('last_activity_at')->nullable()->after('checkout_started_at');
            $table->timestamp('recovery_opened_at')->nullable()->after('reminded_at');
            $table->unsignedSmallInteger('reminder_count')->default(0)->after('recovery_opened_at');
            $table->json('last_reminder_channels')->nullable()->after('reminder_count');
            $table->foreignId('recovered_order_id')->nullable()->after('last_reminder_channels')->constrained('orders')->nullOnDelete();
            $table->timestamp('converted_at')->nullable()->after('recovered_order_id');
            $table->timestamp('recovered_at')->nullable()->after('converted_at');

            $table->index(['company_id', 'status', 'checkout_started_at'], 'storefront_cart_recovery_status_index');
            $table->index(['company_id', 'recovered_at'], 'storefront_cart_recovered_index');
        });

        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->boolean('checkout_autosave_enabled')->default(false)->after('abandoned_cart_reminders_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropColumn('checkout_autosave_enabled');
        });

        Schema::table('storefront_cart_records', function (Blueprint $table): void {
            $table->dropIndex('storefront_cart_recovery_status_index');
            $table->dropIndex('storefront_cart_recovered_index');
            $table->dropConstrainedForeignId('recovered_order_id');
            $table->dropColumn([
                'email',
                'address',
                'subtotal',
                'checkout_started_at',
                'last_activity_at',
                'recovery_opened_at',
                'reminder_count',
                'last_reminder_channels',
                'converted_at',
                'recovered_at',
            ]);
        });
    }
};
