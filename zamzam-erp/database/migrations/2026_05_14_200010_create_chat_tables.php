<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── WhatsApp Providers ────────────────────────────
        Schema::create('whatsapp_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('driver_class')->nullable();
            $table->enum('api_type', ['official', 'unofficial', 'hybrid']);
            $table->string('base_url', 500)->nullable();
            $table->json('auth_config');
            $table->json('webhook_config')->nullable();
            $table->json('capabilities');
            $table->json('rate_limits')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('priority')->default(0);
            $table->string('phone_number', 20)->nullable();
            $table->string('phone_number_id', 100)->nullable();
            $table->string('business_account_id', 100)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'is_default'], 'idx_wa_providers_active_default');
        });

        // ─── WA Provider API Mappings ──────────────────────
        Schema::create('whatsapp_provider_api_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('whatsapp_providers')->cascadeOnDelete();
            $table->enum('action', ['send_text', 'send_media', 'send_template', 'send_buttons', 'send_list', 'send_location', 'mark_read', 'check_number']);
            $table->enum('method', ['POST', 'GET', 'PUT'])->default('POST');
            $table->string('endpoint', 500);
            $table->json('headers_template');
            $table->json('body_template');
            $table->json('response_mapping')->nullable();
            $table->json('error_mapping')->nullable();
            $table->timestamps();

            $table->unique(['provider_id', 'action'], 'wa_api_mappings_provider_action');
        });

        // ─── Conversations ─────────────────────────────────
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('conversation_uuid', 50)->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('channel', ['messenger', 'whatsapp']);
            $table->foreignId('whatsapp_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel_conversation_id');
            $table->string('channel_customer_id');
            $table->string('channel_customer_name')->nullable();
            $table->string('channel_customer_avatar', 500)->nullable();
            $table->enum('status', ['active', 'idle', 'closed'])->default('active');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_ai_active')->default(true);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_human_reply_at')->nullable();
            $table->timestamp('last_ai_reply_at')->nullable();
            $table->unsignedBigInteger('active_workflow_id')->nullable();
            $table->json('tags')->nullable();
            $table->json('ai_context')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['channel', 'channel_customer_id'], 'idx_conversations_channel_customer');
            $table->index('status', 'idx_conversations_status');
            $table->index('customer_id', 'idx_conversations_customer');
        });

        // ─── Conversation Messages ─────────────────────────
        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('message_id')->unique();
            $table->enum('sender_type', ['customer', 'ai_agent', 'human_agent', 'system']);
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('content');
            $table->enum('content_type', ['text', 'image', 'file', 'product_card', 'order_card', 'payment_link', 'quick_reply', 'location', 'audio', 'video', 'sticker'])->default('text');
            $table->json('attachments')->nullable();
            $table->string('intent_detected', 100)->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->boolean('replied_within_50s')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['conversation_id', 'created_at'], 'idx_conv_msgs_conversation');
            $table->index(['sender_type', 'sender_id'], 'idx_conv_msgs_sender');
            $table->index('intent_detected', 'idx_conv_msgs_intent');
        });

        // ─── Chat Carts ────────────────────────────────────
        Schema::create('chat_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['active', 'converted_to_order', 'abandoned', 'expired'])->default('active');
            $table->decimal('total_bdt', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('converted_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->timestamps();
        });

        // ─── Chat Cart Items ───────────────────────────────
        Schema::create('chat_cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('qty');
            $table->decimal('price_bdt', 12, 2);
            $table->decimal('subtotal_bdt', 14, 2);
            $table->enum('added_by', ['ai_agent', 'human_agent', 'customer']);
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // ─── Agent Actions ─────────────────────────────────
        Schema::create('agent_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained();
            $table->foreignId('message_id')->nullable()->constrained('conversation_messages')->nullOnDelete();
            $table->enum('action_type', ['product_search', 'add_to_cart', 'remove_from_cart', 'clear_cart', 'place_order', 'check_order_status', 'check_payment_status', 'send_payment_link', 'check_stock', 'get_price', 'create_customer', 'update_customer', 'send_return_request']);
            $table->json('action_data')->nullable();
            $table->json('action_result')->nullable();
            $table->enum('status', ['pending', 'executed', 'failed', 'requires_approval'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('conversation_id', 'idx_agent_actions_conversation');
            $table->index(['action_type', 'status'], 'idx_agent_actions_type_status');
        });

        // ─── Conversation Tags ─────────────────────────────
        Schema::create('conversation_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('color', 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
        });

        // ─── Quick Reply Templates ─────────────────────────
        Schema::create('quick_reply_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('content');
            $table->string('category', 100)->nullable();
            $table->string('language', 5)->default('bn');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();

            $table->index(['category', 'language'], 'idx_quick_replies_category_lang');
        });

        // ─── WA Provider Logs ──────────────────────────────
        Schema::create('whatsapp_provider_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('whatsapp_providers');
            $table->enum('direction', ['incoming', 'outgoing']);
            $table->string('message_id')->nullable();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 20);
            $table->json('payload')->nullable();
            $table->enum('status', ['sent', 'delivered', 'read', 'failed', 'rate_limited']);
            $table->text('error_message')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['provider_id', 'created_at'], 'idx_wa_logs_provider_date');
            $table->index('status', 'idx_wa_logs_status');
            $table->index('phone', 'idx_wa_logs_phone');
        });

        // ─── Chatbot Workflows ─────────────────────────────
        Schema::create('chatbot_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('trigger_type', ['incoming_message', 'keyword', 'intent', 'schedule', 'event']);
            $table->json('trigger_config')->nullable();
            $table->json('nodes');
            $table->json('edges');
            $table->enum('status', ['active', 'draft', 'archived'])->default('draft');
            $table->integer('version')->default(1);
            $table->boolean('is_default')->default(false);
            $table->enum('channel', ['all', 'whatsapp', 'messenger'])->nullable();
            $table->integer('priority')->default(0);
            $table->integer('execution_count')->default(0);
            $table->timestamp('last_executed_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['trigger_type', 'status'], 'idx_workflows_trigger_status');
            $table->index(['channel', 'priority'], 'idx_workflows_channel_priority');
        });

        // ─── Chatbot Workflow Executions ───────────────────
        Schema::create('chatbot_workflow_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('chatbot_workflows');
            $table->foreignId('conversation_id')->constrained();
            $table->foreignId('message_id')->nullable()->constrained('conversation_messages')->nullOnDelete();
            $table->json('executed_nodes')->nullable();
            $table->enum('status', ['running', 'completed', 'failed', 'paused'])->default('running');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['workflow_id', 'created_at'], 'idx_wf_exec_workflow');
            $table->index('conversation_id', 'idx_wf_exec_conversation');
            $table->index('status', 'idx_wf_exec_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_workflow_executions');
        Schema::dropIfExists('chatbot_workflows');
        Schema::dropIfExists('whatsapp_provider_logs');
        Schema::dropIfExists('quick_reply_templates');
        Schema::dropIfExists('conversation_tags');
        Schema::dropIfExists('agent_actions');
        Schema::dropIfExists('chat_cart_items');
        Schema::dropIfExists('chat_carts');
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('whatsapp_provider_api_mappings');
        Schema::dropIfExists('whatsapp_providers');
    }
};
