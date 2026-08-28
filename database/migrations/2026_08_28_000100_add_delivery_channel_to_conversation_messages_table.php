<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table) {
            // Null means "delivered over the conversation's own provider" (the
            // common case). Set to 'sms' when a WhatsApp send failed and this
            // message was actually delivered as an SMS fallback instead, so the
            // Inbox thread can show that without losing the audit trail of the
            // original failed WhatsApp attempt.
            $table->string('delivery_channel', 20)->nullable()->after('delivery_status');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->dropColumn('delivery_channel');
        });
    }
};
