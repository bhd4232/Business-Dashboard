<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('entry_point', 20)->nullable()->after('provider'); // 'ctwa_ad' | null
            $table->string('ad_referral_id')->nullable()->after('entry_point');
            $table->boolean('ai_enabled')->default(true)->after('status');
            $table->dateTime('human_handled_until')->nullable()->after('ai_enabled');
        });

        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->string('generated_by', 10)->default('human')->after('sent_by'); // human | ai
            $table->decimal('ai_confidence', 4, 3)->nullable()->after('generated_by');
            $table->json('ai_meta')->nullable()->after('ai_confidence'); // tool trace + token usage
        });

        Schema::create('company_faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('question');
            $table->text('answer');
            $table->string('keywords')->nullable(); // comma-separated trigger words for the no-LLM shortcut
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_faqs');

        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->dropColumn(['generated_by', 'ai_confidence', 'ai_meta']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['entry_point', 'ad_referral_id', 'ai_enabled', 'human_handled_until']);
        });
    }
};
