<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_channels', function (Blueprint $table) {
            // 'cloud_api' (today's manual credential-entry flow) | 'coexistence'
            // (connected via Meta Embedded Signup, phone app keeps working too).
            // A flag on the existing 'whatsapp' provider, not a new provider value.
            $table->string('connection_type', 20)->default('cloud_api')->after('provider');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_channels', function (Blueprint $table) {
            $table->dropColumn('connection_type');
        });
    }
};
