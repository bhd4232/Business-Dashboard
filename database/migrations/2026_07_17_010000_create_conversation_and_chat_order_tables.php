<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20); // whatsapp | messenger
            $table->string('external_id'); // WABA phone_number_id or FB page_id
            $table->string('display_name');
            $table->text('access_token')->nullable(); // encrypted cast
            $table->text('app_secret')->nullable(); // encrypted cast — webhook signature verification
            $table->string('verify_token')->nullable(); // webhook subscription handshake
            $table->boolean('auto_create_leads')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['provider', 'external_id']);
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained('conversation_channels')->nullOnDelete();
            $table->string('provider', 20); // whatsapp | messenger | phone | manual
            $table->string('external_contact_id')->nullable(); // WA number / Messenger PSID
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('open'); // open | pending | closed
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('last_message_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'status', 'last_message_at']);
            $table->unique(['channel_id', 'external_contact_id']);
        });

        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 10); // incoming | outgoing
            $table->string('type', 20)->default('text'); // text|image|audio|video|document|sticker|template|order_form|note
            $table->text('body')->nullable();
            $table->string('media_path')->nullable();
            $table->string('media_mime')->nullable();
            $table->string('external_message_id')->nullable(); // wamid / messenger mid — dedupe
            $table->string('delivery_status', 20)->default('received');
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('raw_payload')->nullable();
            $table->dateTime('sent_at');
            $table->timestamps();

            $table->unique('external_message_id');
            $table->index(['conversation_id', 'sent_at']);
        });

        Schema::create('chat_order_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('token', 40)->unique();
            $table->json('prefill'); // items, name, phone, address
            $table->dateTime('expires_at');
            $table->foreignId('converted_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->dateTime('opened_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_order_links');
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('conversation_channels');
    }
};
