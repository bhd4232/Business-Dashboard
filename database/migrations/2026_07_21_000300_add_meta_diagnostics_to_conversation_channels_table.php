<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_channels', function (Blueprint $table): void {
            $table->string('waba_id')->nullable()->after('external_id');
            $table->timestamp('webhook_verified_at')->nullable()->after('is_active');
            $table->timestamp('webhook_subscribed_at')->nullable()->after('webhook_verified_at');
            $table->timestamp('last_webhook_at')->nullable()->after('webhook_subscribed_at');
            $table->timestamp('last_inbound_at')->nullable()->after('last_webhook_at');
            $table->timestamp('last_outbound_at')->nullable()->after('last_inbound_at');
            $table->timestamp('last_health_at')->nullable()->after('last_outbound_at');
            $table->string('last_error_source', 50)->nullable()->after('last_health_at');
            $table->timestamp('last_error_at')->nullable()->after('last_error_source');
            $table->text('last_error')->nullable()->after('last_error_at');

            $table->index(['provider', 'waba_id']);
        });
    }

    public function down(): void
    {
        Schema::table('conversation_channels', function (Blueprint $table): void {
            $table->dropIndex(['provider', 'waba_id']);
            $table->dropColumn([
                'waba_id',
                'webhook_verified_at',
                'webhook_subscribed_at',
                'last_webhook_at',
                'last_inbound_at',
                'last_outbound_at',
                'last_health_at',
                'last_error_source',
                'last_error_at',
                'last_error',
            ]);
        });
    }
};
