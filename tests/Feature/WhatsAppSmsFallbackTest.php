<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\ConversationChannel;
use App\Models\ConversationMessage;
use App\Models\StorefrontSetting;
use App\Services\CompanyContext;
use App\Services\Crm\ConversationMessengerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppSmsFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected ConversationChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'Fallback Co',
            'slug' => 'fallback-co',
            'invoice_prefix' => 'FB',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        app(CompanyContext::class)->set($this->company);

        $this->channel = ConversationChannel::query()->create([
            'provider' => 'whatsapp',
            'external_id' => 'fallback-phone-id',
            'waba_id' => 'fallback-waba-id',
            'display_name' => 'Fallback WhatsApp',
            'access_token' => 'fallback-token',
            'app_secret' => 'fallback-secret',
            'verify_token' => 'fallback-verify',
            'is_active' => true,
        ]);
    }

    public function test_whatsapp_failure_falls_back_to_sms_and_records_the_channel_used(): void
    {
        $this->configureSmsGateway();
        $conversation = $this->conversationWithInbound();

        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Reply window is closed', 'code' => 131047]], 400),
            'sms.example.test/*' => Http::response('OK', 200),
        ]);

        $message = app(ConversationMessengerService::class)->send($conversation, 'Original WhatsApp attempt');

        $this->assertSame('failed', $message->delivery_status);

        $fallback = ConversationMessage::query()
            ->where('conversation_id', $conversation->getKey())
            ->where('delivery_channel', 'sms')
            ->first();

        $this->assertNotNull($fallback);
        $this->assertSame('sent', $fallback->delivery_status);
        $this->assertSame('Original WhatsApp attempt', $fallback->body);
        $this->assertSame(2, ConversationMessage::query()->where('conversation_id', $conversation->getKey())->where('direction', 'outgoing')->count());
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'sms.example.test'));
    }

    public function test_no_fallback_message_created_when_sms_gateway_is_not_configured(): void
    {
        $conversation = $this->conversationWithInbound();

        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Reply window is closed', 'code' => 131047]], 400),
        ]);

        $message = app(ConversationMessengerService::class)->send($conversation, 'No gateway configured');

        $this->assertSame('failed', $message->delivery_status);
        $this->assertSame(1, ConversationMessage::query()->where('conversation_id', $conversation->getKey())->where('direction', 'outgoing')->count());
    }

    public function test_successful_whatsapp_send_never_triggers_an_sms_fallback(): void
    {
        $this->configureSmsGateway();
        $conversation = $this->conversationWithInbound();

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.success']]]),
        ]);

        $message = app(ConversationMessengerService::class)->send($conversation, 'Delivered fine');

        $this->assertSame('sent', $message->delivery_status);
        $this->assertSame(1, ConversationMessage::query()->where('conversation_id', $conversation->getKey())->where('direction', 'outgoing')->count());
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'sms.example.test'));
    }

    protected function configureSmsGateway(): void
    {
        StorefrontSetting::query()->create([
            'company_id' => $this->company->getKey(),
            'is_published' => true,
            'new_customer_delivery_advance_enabled' => false,
            'notification_credentials' => [
                'sms_api_url' => 'http://sms.example.test/send?key={api_key}&to={phone}&msg={message}&from={sender_id}',
                'sms_api_key' => 'sms_key',
                'sms_sender_id' => 'FALLBACK',
            ],
        ]);
    }

    protected function conversationWithInbound(): Conversation
    {
        $conversation = Conversation::query()->create([
            'channel_id' => $this->channel->getKey(),
            'provider' => 'whatsapp',
            'external_contact_id' => '8801812345678',
            'contact_name' => 'Fallback Customer',
            'contact_phone' => '8801812345678',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        ConversationMessage::query()->create([
            'conversation_id' => $conversation->getKey(),
            'direction' => 'incoming',
            'type' => 'text',
            'body' => 'Hello',
            'external_message_id' => 'wamid.fallback.inbound',
            'delivery_status' => 'received',
            'sent_at' => now(),
        ]);

        return $conversation->fresh();
    }
}
