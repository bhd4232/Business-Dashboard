<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courier_webhook_logs', function (Blueprint $table): void {
            $table->string('delivery_id')->nullable()->after('event');
            $table->string('status')->default('pending')->after('payload');
            $table->unsignedSmallInteger('attempts')->default(0)->after('status');
            $table->text('error')->nullable()->after('attempts');
            $table->unique(['courier_provider_id', 'delivery_id'], 'courier_webhook_delivery_unique');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('courier_webhook_logs', function (Blueprint $table): void {
            $table->dropUnique('courier_webhook_delivery_unique');
            $table->dropIndex(['status', 'created_at']);
            $table->dropColumn(['delivery_id', 'status', 'attempts', 'error']);
        });
    }
};
