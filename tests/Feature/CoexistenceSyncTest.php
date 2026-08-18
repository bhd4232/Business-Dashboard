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

/**
 * Covers the three Coexistence-only webhook fields (smb_app_state_sync,
 * history, smb_message_echoes) that MetaWebhookController/StoreIncomingMessageJob
 * route alongside the standard 'messages' field. See tests/Feature/ConversationIngestTest.php
 * for the standard live-message flow this reuses.
 */
class CoexistenceSyncTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected ConversationChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->company = Company::query()->create([
            'name' => 'Sync Co', 'slug' => 'sync-co', 'invoice_prefix' => 'SY',
            'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);

        app(CompanyContext::class)->set($this->company);

        $this->channel = ConversationChannel::query()->create([
            'provider' => 'whatsapp',
            'connection_type' => 'coexistence',
            'external_id' => '111222333',
            'waba_id' => 'waba-1',
            'display_name' => 'Sync Co WhatsApp',
            'access_token' => 'test-token',
            'app_secret' => 'test-secret',
            'verify_token' => 'verify-me',
            'auto_create_leads' => true,
            'is_active' => true,
        ]);

        app(CompanyContext::class)->clear();
    }

    protected function postWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($payload);
        $headers = [
            'Content-Type' => 'application/json',
            'X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $body, 'test-secret'),
        ];

        return $this->call('POST', '/webhooks/meta', [], [], [], $this->transformHeadersToServerVars($headers), $body);
    }

    protected function fieldPayload(string $field, array $value): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'waba-1',
                'changes' => [[
                    'field' => $field,
                    'value' => array_merge(['metadata' => ['phone_number_id' => '111222333']], $value),
                ]],
            ]],
        ];
    }

    public function test_smb_app_state_sync_creates_a_lead_for_a_new_contact(): void
    {
        $this->postWebhook($this->fieldPayload('smb_app_state_sync', [
            'state_sync' => [[
                'type' => 'contact',
                'contact' => ['full_name' => 'Rina Akter', 'phone_number' => '8801912345678'],
                'action' => 'add',
            ]],
        ]))->assertOk();

        $lead = Lead::withoutGlobalScopes()->where('company_id', $this->company->id)->first();
        $this->assertNotNull($lead);
        $this->assertSame('Rina Akter', $lead->name);
        $this->assertSame('8801912345678', $lead->phone);
        $this->assertSame('whatsapp', $lead->source);
    }

    public function test_smb_app_state_sync_skips_a_contact_that_already_matches_a_customer(): void
    {
        app(CompanyContext::class)->set($this->company);
        Customer::query()->create(['name' => 'Existing Customer', 'phone' => '01912345678']);
        app(CompanyContext::class)->clear();

        $this->postWebhook($this->fieldPayload('smb_app_state_sync', [
            'state_sync' => [[
                'type' => 'contact',
                'contact' => ['full_name' => 'Rina Akter', 'phone_number' => '8801912345678'],
                'action' => 'add',
            ]],
        ]))->assertOk();

        $this->assertSame(0, Lead::withoutGlobalScopes()->where('company_id', $this->company->id)->count());
    }

    public function test_smb_app_state_sync_skips_remove_action(): void
    {
        $this->postWebhook($this->fieldPayload('smb_app_state_sync', [
            'state_sync' => [[
                'type' => 'contact',
                'contact' => ['full_name' => 'Rina Akter', 'phone_number' => '8801912345678'],
                'action' => 'remove',
            ]],
        ]))->assertOk();

        $this->assertSame(0, Lead::withoutGlobalScopes()->where('company_id', $this->company->id)->count());
    }

    protected function historyPayload(): array
    {
        return $this->fieldPayload('history', [
            'history' => [[
                'metadata' => ['phase' => '1', 'chunk_order' => '1', 'progress' => '100'],
                'threads' => [[
                    'id' => '8801812345678',
                    'messages' => [
                        [
                            'from' => '8801812345678',
                            'to' => '111222333',
                            'id' => 'wamid.history.in.1',
                            'timestamp' => (string) now()->subDays(10)->timestamp,
                            'type' => 'text',
                            'text' => ['body' => 'দাম কত?'],
                        ],
                        [
                            'from' => '111222333',
                            'to' => '8801812345678',
                            'id' => 'wamid.history.out.1',
                            'timestamp' => (string) now()->subDays(10)->addMinutes(5)->timestamp,
                            'type' => 'text',
                            'text' => ['body' => '৫০০ টাকা'],
                        ],
                    ],
                ]],
            ]],
        ]);
    }

    public function test_history_import_creates_both_directions_of_a_past_conversation(): void
    {
        $this->postWebhook($this->historyPayload())->assertOk();

        $conversation = Conversation::withoutGlobalScopes()->where('external_contact_id', '8801812345678')->first();
        $this->assertNotNull($conversation);
        $this->assertSame($this->company->getKey(), $conversation->company_id);

        $incoming = ConversationMessage::query()->where('external_message_id', 'wamid.history.in.1')->first();
        $this->assertNotNull($incoming);
        $this->assertSame('incoming', $incoming->direction);
        $this->assertSame('received', $incoming->delivery_status);

        $outgoing = ConversationMessage::query()->where('external_message_id', 'wamid.history.out.1')->first();
        $this->assertNotNull($outgoing);
        $this->assertSame('outgoing', $outgoing->direction);
        $this->assertSame('phone_app', $outgoing->generated_by);
        $this->assertSame('sent', $outgoing->delivery_status);
    }

    public function test_history_import_is_idempotent_across_repeated_deliveries(): void
    {
        $payload = $this->historyPayload();

        $this->postWebhook($payload)->assertOk();
        $this->postWebhook($payload)->assertOk();

        $this->assertSame(1, ConversationMessage::query()->where('external_message_id', 'wamid.history.in.1')->count());
        $this->assertSame(1, ConversationMessage::query()->where('external_message_id', 'wamid.history.out.1')->count());
    }

    public function test_history_import_does_not_bump_unread_count(): void
    {
        $this->postWebhook($this->historyPayload())->assertOk();

        $conversation = Conversation::withoutGlobalScopes()->where('external_contact_id', '8801812345678')->first();
        $this->assertSame(0, $conversation->unread_count);
    }

    public function test_message_echo_is_stored_as_phone_app_generated_outgoing_message(): void
    {
        $this->postWebhook($this->fieldPayload('smb_message_echoes', [
            'message_echoes' => [[
                'from' => '111222333',
                'to' => '8801812345678',
                'id' => 'wamid.echo.1',
                'timestamp' => (string) now()->timestamp,
                'type' => 'text',
                'text' => ['body' => 'কিছুক্ষণের মধ্যে পাঠাচ্ছি'],
            ]],
        ]))->assertOk();

        $message = ConversationMessage::query()->where('external_message_id', 'wamid.echo.1')->first();
        $this->assertNotNull($message);
        $this->assertSame('outgoing', $message->direction);
        $this->assertSame('phone_app', $message->generated_by);
        $this->assertSame('কিছুক্ষণের মধ্যে পাঠাচ্ছি', $message->body);

        $conversation = Conversation::withoutGlobalScopes()->find($message->conversation_id);
        $this->assertSame($this->company->getKey(), $conversation->company_id);
    }

    public function test_message_echo_is_skipped_when_the_message_already_exists(): void
    {
        app(CompanyContext::class)->set($this->company);
        $conversation = Conversation::query()->create([
            'channel_id' => $this->channel->getKey(),
            'external_contact_id' => '8801812345678',
            'provider' => 'whatsapp',
            'status' => 'open',
        ]);
        ConversationMessage::query()->create([
            'conversation_id' => $conversation->getKey(),
            'direction' => 'outgoing',
            'type' => 'text',
            'body' => 'Sent through the ERP already',
            'external_message_id' => 'wamid.echo.dup',
            'delivery_status' => 'sent',
            'generated_by' => 'human',
            'sent_at' => now(),
        ]);
        app(CompanyContext::class)->clear();

        $this->postWebhook($this->fieldPayload('smb_message_echoes', [
            'message_echoes' => [[
                'from' => '111222333',
                'to' => '8801812345678',
                'id' => 'wamid.echo.dup',
                'timestamp' => (string) now()->timestamp,
                'type' => 'text',
                'text' => ['body' => 'This should not overwrite the original'],
            ]],
        ]))->assertOk();

        $message = ConversationMessage::query()->where('external_message_id', 'wamid.echo.dup')->first();
        $this->assertSame('human', $message->generated_by);
        $this->assertSame('Sent through the ERP already', $message->body);
    }
}
