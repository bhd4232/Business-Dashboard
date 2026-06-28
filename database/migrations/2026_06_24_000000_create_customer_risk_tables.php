<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_risk_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 40)->index();
            $table->unsignedInteger('total_courier_orders')->default(0);
            $table->unsignedInteger('delivered_orders')->default(0);
            $table->unsignedInteger('returned_orders')->default(0);
            $table->unsignedInteger('cancelled_orders')->default(0);
            $table->decimal('success_ratio', 5, 2)->default(0);
            $table->decimal('return_ratio', 5, 2)->default(0);
            $table->decimal('cancel_ratio', 5, 2)->default(0);
            $table->unsignedTinyInteger('risk_score')->default(100);
            $table->string('risk_level')->default('low');
            $table->boolean('is_blacklisted')->default(false);
            $table->json('factors')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'phone']);
            $table->index(['company_id', 'risk_level', 'risk_score']);
        });

        Schema::create('customer_blacklists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('phone', 40)->nullable()->index();
            $table->text('address')->nullable();
            $table->text('reason');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('fraud_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 40)->nullable();
            $table->unsignedTinyInteger('risk_score');
            $table->string('risk_level');
            $table->json('factors')->nullable();
            $table->boolean('is_blacklisted')->default(false);
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'risk_level', 'created_at']);
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('customer_risk_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_risk_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type');
            $table->integer('score_change')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'event_type']);
            $table->index(['company_id', 'event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_risk_events');
        Schema::dropIfExists('fraud_checks');
        Schema::dropIfExists('customer_blacklists');
        Schema::dropIfExists('customer_risk_profiles');
    }
};
