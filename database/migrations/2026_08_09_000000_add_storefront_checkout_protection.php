<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->string('checkout_policy_mode', 20)->default('off')->after('cod_enabled');
            $table->boolean('checkout_validate_bd_phone')->default(true)->after('checkout_policy_mode');
            $table->boolean('checkout_block_phone')->default(true)->after('checkout_validate_bd_phone');
            $table->boolean('checkout_block_email')->default(true)->after('checkout_block_phone');
            $table->boolean('checkout_block_ip')->default(true)->after('checkout_block_email');
            $table->boolean('checkout_order_limit_enabled')->default(false)->after('checkout_block_ip');
            $table->unsignedSmallInteger('checkout_order_limit_count')->default(1)->after('checkout_order_limit_enabled');
            $table->unsignedSmallInteger('checkout_order_limit_hours')->default(24)->after('checkout_order_limit_count');
            $table->string('checkout_policy_contact_phone', 40)->nullable()->after('checkout_order_limit_hours');
        });

        Schema::table('customer_blacklists', function (Blueprint $table): void {
            $table->string('email')->nullable()->after('phone')->index();
            $table->string('ip_address', 45)->nullable()->after('email')->index();
        });

        Schema::create('storefront_checkout_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone_hash', 64)->nullable();
            $table->string('email_hash', 64)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('phone_mask', 40)->nullable();
            $table->string('email_mask')->nullable();
            $table->string('ip_mask', 45)->nullable();
            $table->string('policy_mode', 20);
            $table->string('outcome', 30);
            $table->json('violations')->nullable();
            $table->decimal('cart_total', 14, 2)->default(0);
            $table->string('payment_method', 40)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'outcome', 'created_at'], 'checkout_attempt_company_outcome_index');
            $table->index(['company_id', 'phone_hash', 'created_at'], 'checkout_attempt_phone_index');
            $table->index(['company_id', 'email_hash', 'created_at'], 'checkout_attempt_email_index');
            $table->index(['company_id', 'ip_hash', 'created_at'], 'checkout_attempt_ip_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_checkout_attempts');

        Schema::table('customer_blacklists', function (Blueprint $table): void {
            $table->dropColumn(['email', 'ip_address']);
        });

        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'checkout_policy_mode',
                'checkout_validate_bd_phone',
                'checkout_block_phone',
                'checkout_block_email',
                'checkout_block_ip',
                'checkout_order_limit_enabled',
                'checkout_order_limit_count',
                'checkout_order_limit_hours',
                'checkout_policy_contact_phone',
            ]);
        });
    }
};
