<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->boolean('risk_payment_enabled')->default(false)->after('checkout_policy_contact_phone');
            $table->unsignedTinyInteger('risk_payment_success_ratio_threshold')->default(70)->after('risk_payment_enabled');
            $table->string('risk_payment_advance_type', 20)->default('fixed')->after('risk_payment_success_ratio_threshold');
            $table->decimal('risk_payment_advance_amount', 10, 2)->default(100)->after('risk_payment_advance_type');
            $table->unsignedTinyInteger('risk_payment_advance_percent')->default(20)->after('risk_payment_advance_amount');
            $table->string('risk_payment_zero_history_action', 30)->default('allow_cod')->after('risk_payment_advance_percent');
            $table->text('risk_payment_customer_message')->nullable()->after('risk_payment_zero_history_action');
        });

        Schema::table('storefront_checkout_attempts', function (Blueprint $table): void {
            $table->decimal('courier_success_ratio', 5, 2)->nullable()->after('violations');
            $table->decimal('required_advance', 14, 2)->default(0)->after('courier_success_ratio');
            $table->json('advance_reasons')->nullable()->after('required_advance');
        });
    }

    public function down(): void
    {
        Schema::table('storefront_checkout_attempts', function (Blueprint $table): void {
            $table->dropColumn(['courier_success_ratio', 'required_advance', 'advance_reasons']);
        });

        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'risk_payment_enabled',
                'risk_payment_success_ratio_threshold',
                'risk_payment_advance_type',
                'risk_payment_advance_amount',
                'risk_payment_advance_percent',
                'risk_payment_zero_history_action',
                'risk_payment_customer_message',
            ]);
        });
    }
};
