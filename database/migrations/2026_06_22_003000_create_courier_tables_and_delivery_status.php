<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'delivery_status')) {
                $table->string('delivery_status')->default('not_booked')->after('status');
            }
        });

        Schema::create('courier_providers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('driver')->default('manual');
            $table->json('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'driver', 'is_active']);
        });

        Schema::create('courier_bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('courier_provider_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('tracking_id')->nullable();
            $table->string('recipient_name');
            $table->string('recipient_phone')->nullable();
            $table->text('recipient_address')->nullable();
            $table->decimal('cod_amount', 12, 2)->default(0);
            $table->string('status')->default('booking_pending');
            $table->timestamp('booked_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'tracking_id']);
            $table->index(['order_id', 'status']);
        });

        Schema::create('courier_status_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('courier_booking_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'to_status']);
            $table->index(['courier_booking_id', 'created_at']);
        });

        Schema::create('courier_webhook_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('courier_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event')->nullable();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_webhook_logs');
        Schema::dropIfExists('courier_status_logs');
        Schema::dropIfExists('courier_bookings');
        Schema::dropIfExists('courier_providers');

        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'delivery_status')) {
                $table->dropColumn('delivery_status');
            }
        });
    }
};
