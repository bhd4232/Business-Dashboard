<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Lets an external order sync (WooCommerce order webhooks) upsert
            // idempotently instead of creating a duplicate order every time a
            // webhook is redelivered — WooCommerce guarantees at-least-once
            // delivery, not exactly-once.
            $table->string('external_reference')->nullable()->after('source');
            $table->unique(['company_id', 'external_reference']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'external_reference']);
            $table->dropColumn('external_reference');
        });
    }
};
