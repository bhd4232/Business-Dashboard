<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->boolean('meta_tracking_enabled')->default(false)->after('checkout_autosave_enabled');
            $table->boolean('meta_browser_tracking_enabled')->default(true)->after('meta_tracking_enabled');
            $table->boolean('meta_capi_enabled')->default(false)->after('meta_browser_tracking_enabled');
            $table->string('meta_pixel_id', 32)->nullable()->after('meta_capi_enabled');
            $table->text('meta_tracking_credentials')->nullable()->after('meta_pixel_id');
        });

        Schema::create('storefront_meta_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_name', 64);
            $table->string('event_id', 128);
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->json('payload_summary')->nullable();
            $table->json('response_summary')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'event_id'], 'storefront_meta_event_unique');
            $table->index(['company_id', 'status', 'created_at'], 'storefront_meta_event_status_index');
            $table->index(['company_id', 'order_id', 'event_name'], 'storefront_meta_order_event_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_meta_events');

        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'meta_tracking_enabled',
                'meta_browser_tracking_enabled',
                'meta_capi_enabled',
                'meta_pixel_id',
                'meta_tracking_credentials',
            ]);
        });
    }
};
