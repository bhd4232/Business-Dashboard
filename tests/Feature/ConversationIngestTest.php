<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\ConversationChannel;
use App\Models\ConversationMessage;
use App\Models\Customer;
use App\Models\Lead;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConversationIngestTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected ConversationChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->company = Company::query()->create([
            'name' => 'Chat Co',
            'slug' => 'chat-co',
            'invoice_prefix' => 'CH',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        app(CompanyContext::class)->set($this->company);

        $this->channel = ConversationChannel::query()->create([
            'provider' => 'whatsapp',
            'external_id' => '111222333',
            'display_name' => 'Chat Co WhatsApp',
            'access_token' => 'test-token',
            'app_secret' => 'test-secret',
            'verify_token' => 'verify-me',
            'auto_create_leads' => true,
            'is_active' => true,
        ]);

        app(CompanyContext::class)->clear();
    }

    protected function whatsAppPayload(string $messageId = 'wamid.test.1', string $from = '8801812345678'): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'waba-1',
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => '111222333'],
                        'contacts' => [['wa_id' => $from, 'profile' => ['name' => 'Chat Customer']]],
                        'messages' => [[
                            'id' => $messageId,
                            'from' => $from,
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'text',
                            'text' => ['body' => 'আসসালামু আলাইকুম, দাম কত?'],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    protected function postWebhook(array $payload, ?string $secret = 'test-secret')
    {
        $body = json_encode($payload);
        $headers = ['Content-Type' => 'application/json'];

        if ($secret !== null) {
            $headers['X-Hub-Signature-256'] = 'sha256='.hash_hmac('sha256', $body, $secret);
        }

        return $this->call('POST', '/webhooks/meta', [], [], [], $this->transformHeadersToServerVars($headers), $body);
    }

    public function test_webhook_verification_handshake(): void
    {
        $this->get('/webhooks/meta?hub_mode=subscribe&hub_verify_token=verify-me&hub_challenge=12345')
            ->assertOk()
            ->assertSee('12345');

        $this->get('/webhooks/meta?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=12345')
            ->assertForbidden();
    }

    public function test_invalid_signature_is_rejected_and_nothing_is_saved(): void
    {
        $this->postWebhook($this->whatsAppPayload(), secret: 'wrong-secret')->assertForbidden();

        $this->assertSame(0, Conversation::withoutGlobalScopes()->count());
        $this->assertSame(0, ConversationMessage::query()->count());
    }

    public function test_incoming_message_creates_scoped_conversation_and_lead(): void
    {
        $this->postWebhook($this->whatsAppPayload())->assertOk();

        $conversation = Conversation::withoutGlobalScopes()->first();
        $this->assertNotNull($conversation);
        $this->assertSame($this->company->getKey(), $conversation->company_id);
        $this->assertSame('whatsapp', $conversation->provider);
        $this->assertSame('Chat Customer', $conversation->contact_name);
        $this->assertSame(1, $conversation->unread_count);

        $message = $conversation->messages()->first();
        $this->assertSame('আসসালামু আলাইকুম, দাম কত?', $message->body);
        $this->assertSame('incoming', $message->direction);

        // Unknown contact → auto-created lead with whatsapp source.
        $lead = Lead::withoutGlobalScopes()->find($conversation->lead_id);
        $this->assertNotNull($lead);
        $this->assertSame('whatsapp', $lead->source);
        $this->assertSame($this->company->getKey(), $lead->company_id);
    }

    public function test_duplicate_external_message_id_is_ignored(): void
    {
        $this->postWebhook($this->whatsAppPayload('wamid.dupe'))->assertOk();
        $this->postWebhook($this->whatsAppPayload('wamid.dupe'))->assertOk();

        $this->assertSame(1, ConversationMessage::query()->count());
        $this->assertSame(1, (int) Conversation::withoutGlobalScopes()->value('unread_count'));
    }

    public function test_known_phone_links_conversation_to_existing_customer(): void
    {
        app(CompanyContext::class)->set($this->company);
        $customer = Customer::query()->create([
            'name' => 'Existing Customer',
            'phone' => '01812345678',
            'opening_balance' => 0,
            'is_active' => true,
        ]);
        app(CompanyContext::class)->clear();

        $this->postWebhook($this->whatsAppPayload(from: '8801812345678'))->assertOk();

        $conversation = Conversation::withoutGlobalScopes()->first();
        $this->assertSame($customer->getKey(), $conversation->customer_id);
        $this->assertNull($conversation->lead_id);
        $this->assertSame(0, Lead::withoutGlobalScopes()->count());
    }

    public function test_conversations_are_isolated_between_companies(): void
    {
        $this->postWebhook($this->whatsAppPayload())->assertOk();

        $other = Company::query()->create([
            'name' => 'Other Co',
            'slug' => 'other-co',
            'invoice_prefix' => 'OC',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        app(CompanyContext::class)->set($other);
        $this->assertSame(0, Conversation::query()->count());

        app(CompanyContext::class)->set($this->company);
        $this->assertSame(1, Conversation::query()->count());
    }

    public function test_unknown_channel_returns_404(): void
    {
        $payload = $this->whatsAppPayload();
        $payload['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] = '999999';

        $this->postWebhook($payload)->assertNotFound();
    }
}
