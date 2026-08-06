<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->decimal('delivery_first_kg_inside', 10, 2)->default(70)->after('delivery_charge_outside');
            $table->decimal('delivery_first_kg_outside', 10, 2)->default(110)->after('delivery_first_kg_inside');
            $table->decimal('delivery_additional_per_kg', 10, 2)->default(20)->after('delivery_first_kg_outside');
            $table->boolean('new_customer_delivery_advance_enabled')->default(true)->after('delivery_additional_per_kg');
            $table->text('new_customer_advance_message')->nullable()->after('new_customer_delivery_advance_enabled');
            $table->string('whatsapp_group_url')->nullable()->after('new_customer_advance_message');
            $table->text('whatsapp_group_message')->nullable()->after('whatsapp_group_url');
            $table->boolean('complaint_telegram_enabled')->default(false)->after('whatsapp_group_message');
            $table->text('telegram_credentials')->nullable()->after('complaint_telegram_enabled');
        });

        Schema::table('storefront_payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('order_id')->nullable()->change();
            $table->string('purpose')->default('order_payment')->after('gateway');
            $table->text('checkout_data')->nullable()->after('payload');
        });

        Schema::create('storefront_complaints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('complaint_number');
            $table->string('order_reference');
            $table->string('customer_name')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('issue_type', 40);
            $table->text('details');
            $table->string('status', 30)->default('new');
            $table->text('internal_note')->nullable();
            $table->string('telegram_status', 30)->default('pending');
            $table->string('telegram_message_id')->nullable();
            $table->text('telegram_error')->nullable();
            $table->timestamp('telegram_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'complaint_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_complaints');

        Schema::table('storefront_payments', function (Blueprint $table): void {
            $table->dropColumn(['purpose', 'checkout_data']);
        });

        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'delivery_first_kg_inside',
                'delivery_first_kg_outside',
                'delivery_additional_per_kg',
                'new_customer_delivery_advance_enabled',
                'new_customer_advance_message',
                'whatsapp_group_url',
                'whatsapp_group_message',
                'complaint_telegram_enabled',
                'telegram_credentials',
            ]);
        });
    }
};
