<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->boolean('meta_consent_required')->default(true)->after('meta_tracking_enabled');
            $table->string('meta_consent_message', 500)->nullable()->after('meta_consent_required');
            $table->boolean('meta_advanced_matching_enabled')->default(false)->after('meta_browser_tracking_enabled');
            $table->json('meta_browser_events')->nullable()->after('meta_advanced_matching_enabled');
            $table->boolean('meta_custom_events_enabled')->default(false)->after('meta_browser_events');
            $table->json('meta_custom_events')->nullable()->after('meta_custom_events_enabled');
            $table->string('meta_purchase_timing', 24)->default('immediate')->after('meta_capi_enabled');
            $table->unsignedTinyInteger('meta_purchase_success_ratio_threshold')->default(70)->after('meta_purchase_timing');
            $table->json('meta_domain_verification_tags')->nullable()->after('meta_pixel_id');
        });

        Schema::create('storefront_meta_attributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('context')->nullable();
            $table->string('purchase_due_stage', 24)->default('immediate');
            $table->boolean('consent_granted')->default(false);
            $table->boolean('recovered')->default(false);
            $table->foreignId('storefront_cart_record_id')->nullable()->constrained('storefront_cart_records')->nullOnDelete();
            $table->timestamp('purchase_dispatched_at')->nullable();
            $table->timestamp('recovered_dispatched_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'purchase_due_stage', 'purchase_dispatched_at'], 'meta_attribution_purchase_index');
        });

        Schema::table('storefront_meta_events', function (Blueprint $table): void {
            $table->dropUnique('storefront_meta_event_unique');
            $table->string('pixel_id', 32)->nullable()->after('order_id');
            $table->foreignId('order_status_transition_id')->nullable()->after('order_id')->constrained('order_status_transitions')->nullOnDelete();
            $table->foreignId('storefront_cart_record_id')->nullable()->after('order_status_transition_id')->constrained('storefront_cart_records')->nullOnDelete();
            $table->unique(['company_id', 'pixel_id', 'event_id'], 'storefront_meta_pixel_event_unique');
        });
    }

    public function down(): void
    {
        Schema::table('storefront_meta_events', function (Blueprint $table): void {
            $table->dropUnique('storefront_meta_pixel_event_unique');
            $table->dropConstrainedForeignId('storefront_cart_record_id');
            $table->dropConstrainedForeignId('order_status_transition_id');
            $table->dropColumn('pixel_id');
            $table->unique(['company_id', 'event_id'], 'storefront_meta_event_unique');
        });

        Schema::dropIfExists('storefront_meta_attributions');

        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'meta_consent_required',
                'meta_consent_message',
                'meta_advanced_matching_enabled',
                'meta_browser_events',
                'meta_custom_events_enabled',
                'meta_custom_events',
                'meta_purchase_timing',
                'meta_purchase_success_ratio_threshold',
                'meta_domain_verification_tags',
            ]);
        });
    }
};
