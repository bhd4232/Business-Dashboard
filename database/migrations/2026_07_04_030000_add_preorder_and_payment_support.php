<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_preorder')->default(false)->after('status');
            $table->unsignedTinyInteger('preorder_advance_percent')->nullable()->after('is_preorder');
        });

        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->boolean('online_payment_enabled')->default(false)->after('theme_mode');
            $table->text('payment_credentials')->nullable()->after('online_payment_enabled');
        });

        Schema::create('storefront_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway')->default('zinipay');
            $table->string('invoice_id')->nullable()->index();
            $table->decimal('amount', 14, 2);
            $table->string('status')->default('pending');
            $table->string('payment_method')->nullable();
            $table->string('transaction_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_payments');

        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropColumn(['online_payment_enabled', 'payment_credentials']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['is_preorder', 'preorder_advance_percent']);
        });
    }
};
