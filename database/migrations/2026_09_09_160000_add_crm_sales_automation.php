<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->json('qualification')->nullable();
            $table->string('temperature')->default('cold')->index();
            $table->boolean('temperature_locked')->default(false);
            $table->unsignedSmallInteger('qualification_score')->default(0);
            $table->text('qualification_summary')->nullable();
        });
        Schema::create('crm_ai_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_message_id')->nullable()->constrained('conversation_messages')->nullOnDelete();
            $table->string('status')->default('running');
            $table->string('reason')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('estimated_cost_usd', 12, 6)->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->unsignedInteger('response_seconds')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('suggested_reply')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'created_at']);
        });
        Schema::create('crm_sales_follow_ups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('event_key')->unique();
            $table->foreignId('chat_order_link_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('inbound_watermark')->nullable();
            $table->timestamp('due_at');
            $table->string('status')->default('pending');
            $table->string('reason')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_sales_follow_ups');
        Schema::dropIfExists('crm_ai_runs');
        Schema::table('leads', fn (Blueprint $table) => $table->dropColumn(['qualification', 'temperature', 'temperature_locked', 'qualification_score', 'qualification_summary']));
    }
};
