<?php

namespace Tests\Feature;

use App\Jobs\AiAutoReplyJob;
use App\Jobs\MarkConversationReadJob;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\ConversationChannel;
use App\Models\ConversationMessage;
use App\Services\CompanyContext;
use App\Services\Crm\ConversationMessengerService;
use App\Services\Meta\MetaGraphException;
use App\Services\Meta\MetaGraphService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MetaMessagingReliabilityTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected ConversationChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.meta.graph_api_version' => 'v25.0']);

        $this->company = $this->makeCompany('Meta Co', 'meta-co', 'MT');
        $this->channel = $this->makeChannel($this->company, '111222333', 'waba-1');
    }

    public function test_whatsapp_health_and_waba_subscription_use_configured_graph_version(): void
    {
        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && str_ends_with($request->url(), '/111222333?fields=id%2Cdisplay_phone_number%2Cverified_name%2Cquality_rating%2Cstatus')) {
                return Http::response(['id' => '111222333', 'display_phone_number' => '+880 1800-000000']);
            }

            if ($request->method() === 'GET' && str_contains($request->url(), '/waba-1/subscribed_apps')) {
                return Http::response(['data' => [['id' => 'some-previous-app']]]);
            }

            if ($request->method() === 'POST' && str_contains($request->url(), '/waba-1/subscribed_apps')) {
                return Http::response(['success' => true]);
            }

            return Http::response(['error' => ['message' => 'Unexpected test URL', 'code' => 100]], 400);
        });

        $result = app(MetaGraphService::class)->testAndSubscribe($this->channel);

        $this->assertTrue((bool) data_get($result, 'subscription.subscribed'));
        $this->assertNotNull($this->channel->fresh()->webhook_subscribed_at);
        $this->assertNull($this->channel->fresh()->last_error);
        $this->assertSame('Verify callback', $this->channel->fresh()->diagnosticStatus());
        $this->channel->markWebhookVerified();
        $this->assertSame('Configured', $this->channel->fresh()->diagnosticStatus());
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://graph.facebook.com/v25.0/'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/waba-1/subscribed_apps')
            && ! str_contains($request->url(), 'access_token'));
    }

    public function test_non_idempotent_message_send_is_not_automatically_retried(): void
    {
        $conversation = $this->conversationWithCurrentInbound();
        Http::fake([
            'graph.facebook.com/*' => Http::sequence()
                ->push(['error' => ['message' => 'Temporary server error', 'code' => 2]], 500)
                ->push(['messages' => [['id' => 'wamid.must-not-send']]]),
        ]);

        $message = app(ConversationMessengerService::class)->send($conversation, 'Send exactly once');

        $this->assertSame('failed', $message->delivery_status);
        Http::assertSentCount(1);
    }

    public function test_expired_token_is_sanitized_and_saved_as_actionable_diagnostic(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => [
                    'message' => 'Error validating access token: EAATESTSECRETTOKEN1234567890. Session has expired.',
                    'type' => 'OAuthException',
                    'code' => 190,
                ],
            ], 401),
        ]);

        try {
            app(MetaGraphService::class)->channelHealth($this->channel);
            $this->fail('Expected an expired-token exception.');
        } catch (MetaGraphException $exception) {
            $this->assertStringContainsString('permanent system-user token', $exception->getMessage());
            $this->assertStringNotContainsString('EAATESTSECRETTOKEN', $exception->getMessage());
        }

        $diagnostic = (string) $this->channel->fresh()->last_error;
        $this->assertStringContainsString('expired or is invalid', $diagnostic);
        $this->assertStringNotContainsString('EAATESTSECRETTOKEN', $diagnostic);
        $this->assertNotNull($this->channel->fresh()->last_error_at);
    }

    public function test_channel_serialization_never_exposes_meta_secrets(): void
    {
        $serialized = json_encode($this->channel->fresh()->toArray());

        $this->assertStringNotContainsString('test-access-token', $serialized);
        $this->assertStringNotContainsString('shared-app-secret', $serialized);
        $this->assertStringNotContainsString('shared-verify-token', $serialized);
    }

    public function test_multi_change_webhook_routes_each_phone_and_persists_before_queue_worker(): void
    {
        $otherCompany = $this->makeCompany('Other Meta Co', 'other-meta-co', 'OM');
        $otherChannel = $this->makeChannel($otherCompany, '444555666', 'waba-2');
        Queue::fake([AiAutoReplyJob::class]);

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                $this->whatsAppEntry($this->channel, 'wamid.multi.1', '8801811111111', 'First message'),
                $this->whatsAppEntry($otherChannel, 'wamid.multi.2', '8801822222222', 'Second message'),
            ],
        ];

        $this->postWebhook($payload)->assertOk()->assertSee('EVENT_RECEIVED');

        $this->assertSame(2, Conversation::withoutGlobalScopes()->count());
        $this->assertSame(2, ConversationMessage::query()->where('direction', 'incoming')->count());
        $this->assertDatabaseHas('conversation_messages', ['external_message_id' => 'wamid.multi.1', 'body' => 'First message']);
        $this->assertDatabaseHas('conversation_messages', ['external_message_id' => 'wamid.multi.2', 'body' => 'Second message']);
        $this->assertNotNull($this->channel->fresh()->last_webhook_at);
        $this->assertNotNull($this->channel->fresh()->last_inbound_at);
        $this->assertNotNull($otherChannel->fresh()->last_inbound_at);
        Queue::assertPushed(AiAutoReplyJob::class, 2);

        foreach (ConversationMessage::query()->where('direction', 'incoming')->get() as $message) {
            Queue::assertPushed(AiAutoReplyJob::class, fn (AiAutoReplyJob $job): bool => $job->conversationId === $message->conversation_id
                && $job->sourceMessageId === $message->getKey());
        }
    }

    public function test_out_of_order_inbound_event_increments_unread_without_regressing_last_message_time(): void
    {
        Queue::fake([AiAutoReplyJob::class]);
        $newerAt = now()->startOfSecond();
        $newer = $this->whatsAppEntry($this->channel, 'wamid.order.newer', '8801811111111', 'Newer');
        $newer['changes'][0]['value']['messages'][0]['timestamp'] = (string) $newerAt->timestamp;
        $older = $this->whatsAppEntry($this->channel, 'wamid.order.older', '8801811111111', 'Older');
        $older['changes'][0]['value']['messages'][0]['timestamp'] = (string) $newerAt->copy()->subHour()->timestamp;

        $this->postWebhook(['object' => 'whatsapp_business_account', 'entry' => [$newer]])->assertOk();
        $this->postWebhook(['object' => 'whatsapp_business_account', 'entry' => [$older]])->assertOk();

        $conversation = Conversation::withoutGlobalScopes()->sole();
        $this->assertSame(2, $conversation->unread_count);
        $this->assertTrue($conversation->last_message_at->equalTo($newerAt));
    }

    public function test_outgoing_attempt_is_archived_as_sending_then_sent_and_uses_v25(): void
    {
        $conversation = $this->conversationWithCurrentInbound();
        $observedSendingState = false;

        Http::fake(function (Request $request) use (&$observedSendingState) {
            $observedSendingState = ConversationMessage::query()
                ->where('direction', 'outgoing')
                ->where('delivery_status', 'sending')
                ->exists();

            return Http::response(['messages' => [['id' => 'wamid.outgoing.success']]]);
        });

        $message = app(ConversationMessengerService::class)->send($conversation, 'Hello from the inbox');

        $this->assertTrue($observedSendingState);
        $this->assertSame('sent', $message->delivery_status);
        $this->assertSame('wamid.outgoing.success', $message->external_message_id);
        $this->assertNotNull($this->channel->fresh()->last_outbound_at);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://graph.facebook.com/v25.0/111222333/messages'
            && ! str_contains($request->url(), 'test-access-token'));
    }

    public function test_outbound_media_is_absolute_for_meta_and_is_omitted_on_loopback_app_urls(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::sequence()
                ->push(['messages' => [['id' => 'wamid.public-media']]])
                ->push(['messages' => [['id' => 'wamid.local-text']]]),
        ]);
        $meta = app(MetaGraphService::class);

        config(['app.url' => 'https://erp.example.test']);
        $meta->sendWhatsApp($this->channel, '8801812345678', 'Product link', '/storage/products/catalog.jpg');

        config(['app.url' => 'http://127.0.0.1:8000']);
        $meta->sendWhatsApp($this->channel, '8801812345678', 'Local product link', '/storage/products/catalog.jpg');

        Http::assertSent(fn (Request $request): bool => $request['type'] === 'image'
            && $request['image']['link'] === 'https://erp.example.test/storage/products/catalog.jpg');
        Http::assertSent(fn (Request $request): bool => $request['type'] === 'text'
            && $request['text']['body'] === 'Local product link'
            && ! isset($request['image']));
        Http::assertSentCount(2);
    }

    public function test_failed_outgoing_attempt_is_archived_safely_and_can_retry_same_bubble(): void
    {
        $conversation = $this->conversationWithCurrentInbound();
        Http::fake([
            'graph.facebook.com/*' => Http::sequence()
                ->push([
                    'error' => [
                        'message' => 'Invalid OAuth access token EAASHOULDNOTBESAVED123456789.',
                        'code' => 190,
                    ],
                ], 401)
                ->push(['messages' => [['id' => 'wamid.retry.success']]]),
        ]);

        $message = app(ConversationMessengerService::class)->send($conversation, 'Please archive this failure');

        $this->assertSame('failed', $message->delivery_status);
        $this->assertStringContainsString('expired or is invalid', (string) data_get($message->raw_payload, 'error.message'));
        $this->assertStringNotContainsString('EAASHOULDNOTBESAVED', json_encode($message->raw_payload));

        $retried = app(ConversationMessengerService::class)->retry($message->refresh());

        $this->assertSame($message->getKey(), $retried->getKey());
        $this->assertSame('sent', $retried->delivery_status, json_encode($retried->raw_payload));
        $this->assertSame('wamid.retry.success', $retried->external_message_id);
        $this->assertSame(2, ConversationMessage::query()->count());
    }

    public function test_mark_latest_incoming_read_is_best_effort_and_idempotent(): void
    {
        $conversation = $this->conversationWithCurrentInbound();
        Http::fake([
            'graph.facebook.com/*' => Http::response(['success' => true]),
        ]);

        $service = app(ConversationMessengerService::class);

        $this->assertTrue($service->markLatestIncomingRead($conversation));
        $this->assertTrue($service->markLatestIncomingRead($conversation));
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://graph.facebook.com/v25.0/111222333/messages'
            && $request['status'] === 'read'
            && $request['message_id'] === 'wamid.current.incoming');
    }

    public function test_outgoing_and_read_receipts_never_use_another_company_channel(): void
    {
        $otherCompany = $this->makeCompany('Foreign Channel Co', 'foreign-channel-co', 'FC');
        $foreignChannel = $this->makeChannel($otherCompany, 'foreign-phone-id', 'foreign-waba-id');
        app(CompanyContext::class)->set($this->company);
        $conversation = Conversation::query()->create([
            'channel_id' => $foreignChannel->getKey(),
            'provider' => 'whatsapp',
            'external_contact_id' => '8801812345678',
            'contact_name' => 'Tenant Boundary Customer',
            'status' => 'open',
            'last_message_at' => now(),
        ]);
        ConversationMessage::query()->create([
            'conversation_id' => $conversation->getKey(),
            'direction' => 'incoming',
            'type' => 'text',
            'body' => 'Do not cross tenants',
            'external_message_id' => 'wamid.cross-tenant.incoming',
            'delivery_status' => 'received',
            'sent_at' => now(),
        ]);
        $conversation->setRelation('channel', $foreignChannel);
        Http::preventStrayRequests();

        $service = app(ConversationMessengerService::class);
        $outgoing = $service->send($conversation, 'Must stay inside the company');

        $this->assertSame('failed', $outgoing->delivery_status);
        $this->assertStringContainsString('owned by another company', (string) data_get($outgoing->raw_payload, 'error.message'));
        $this->assertFalse($service->markLatestIncomingRead($conversation));
        $this->assertNull($foreignChannel->fresh()->last_error);
        Http::assertNothingSent();
    }

    public function test_read_receipt_is_queued_without_running_during_selection(): void
    {
        Bus::fake([MarkConversationReadJob::class]);
        Http::preventStrayRequests();
        $conversation = $this->conversationWithCurrentInbound();

        app(ConversationMessengerService::class)->dispatchLatestIncomingRead($conversation);

        Bus::assertDispatched(
            MarkConversationReadJob::class,
            fn (MarkConversationReadJob $job): bool => $job->conversationId === $conversation->getKey(),
        );
        Http::assertNothingSent();
    }

    public function test_delivery_webhook_handles_played_and_sanitized_failure_details(): void
    {
        $conversation = $this->conversationWithCurrentInbound();
        $outgoing = ConversationMessage::query()->create([
            'conversation_id' => $conversation->getKey(),
            'direction' => 'outgoing',
            'type' => 'audio',
            'body' => 'Voice reply',
            'external_message_id' => 'wamid.status.target',
            'delivery_status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->postWebhook($this->statusPayload('played'))->assertOk();
        $this->assertSame('played', $outgoing->refresh()->delivery_status);

        $this->postWebhook($this->statusPayload('failed', [[
            'code' => 131047,
            'title' => 'Re-engagement message',
            'message' => 'Message failed because more than 24 hours have passed.',
        ]]))->assertOk();

        $this->assertSame('failed', $outgoing->refresh()->delivery_status);
        $this->assertStringContainsString('reply window is closed', (string) data_get($outgoing->raw_payload, '_delivery.error'));
        $this->assertStringContainsString('reply window is closed', (string) $this->channel->fresh()->last_error);

        $this->postWebhook($this->statusPayload('sent'))->assertOk();
        $this->assertSame('failed', $outgoing->refresh()->delivery_status);
    }

    public function test_delivery_status_does_not_regress_from_read_to_delivered(): void
    {
        $conversation = $this->conversationWithCurrentInbound();
        $outgoing = ConversationMessage::query()->create([
            'conversation_id' => $conversation->getKey(),
            'direction' => 'outgoing',
            'type' => 'text',
            'body' => 'Already read',
            'external_message_id' => 'wamid.status.target',
            'delivery_status' => 'read',
            'sent_at' => now(),
        ]);

        $this->postWebhook($this->statusPayload('delivered'))->assertOk();

        $this->assertSame('read', $outgoing->refresh()->delivery_status);
    }

    public function test_invalid_signature_cannot_poison_channel_diagnostics(): void
    {
        $payload = ['object' => 'whatsapp_business_account', 'entry' => [
            $this->whatsAppEntry($this->channel, 'wamid.invalid.signature', '8801811111111', 'Invalid'),
        ]];
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

        $this->call(
            'POST',
            '/webhooks/meta',
            [],
            [],
            [],
            $this->transformHeadersToServerVars([
                'Content-Type' => 'application/json',
                'X-Hub-Signature-256' => 'sha256='.str_repeat('0', 64),
            ]),
            $body,
        )->assertForbidden();

        $this->assertNull($this->channel->fresh()->last_error);
        $this->assertNull($this->channel->fresh()->last_error_at);
    }

    public function test_meta_media_download_rejects_untrusted_hosts_before_http_request(): void
    {
        Http::preventStrayRequests();

        $this->expectException(MetaGraphException::class);
        $this->expectExceptionMessage('untrusted media URL');

        app(MetaGraphService::class)->downloadMedia($this->channel, 'https://attacker.example.test/private');
    }

    protected function makeCompany(string $name, string $slug, string $prefix): Company
    {
        return Company::query()->create([
            'name' => $name,
            'slug' => $slug,
            'invoice_prefix' => $prefix,
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
    }

    protected function makeChannel(Company $company, string $phoneNumberId, string $wabaId): ConversationChannel
    {
        app(CompanyContext::class)->set($company);

        try {
            return ConversationChannel::query()->create([
                'provider' => 'whatsapp',
                'external_id' => $phoneNumberId,
                'waba_id' => $wabaId,
                'display_name' => $company->name.' WhatsApp',
                'access_token' => 'test-access-token',
                'app_secret' => 'shared-app-secret',
                'verify_token' => 'shared-verify-token',
                'auto_create_leads' => false,
                'is_active' => true,
            ]);
        } finally {
            app(CompanyContext::class)->clear();
        }
    }

    protected function conversationWithCurrentInbound(): Conversation
    {
        app(CompanyContext::class)->set($this->company);

        $conversation = Conversation::query()->create([
            'channel_id' => $this->channel->getKey(),
            'provider' => 'whatsapp',
            'external_contact_id' => '8801812345678',
            'contact_name' => 'Meta Customer',
            'contact_phone' => '8801812345678',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        ConversationMessage::query()->create([
            'conversation_id' => $conversation->getKey(),
            'direction' => 'incoming',
            'type' => 'text',
            'body' => 'Current inbound message',
            'external_message_id' => 'wamid.current.incoming',
            'delivery_status' => 'received',
            'raw_payload' => ['id' => 'wamid.current.incoming'],
            'sent_at' => now(),
        ]);

        app(CompanyContext::class)->clear();

        return $conversation->fresh();
    }

    protected function whatsAppEntry(
        ConversationChannel $channel,
        string $messageId,
        string $from,
        string $body,
    ): array {
        return [
            'id' => $channel->waba_id,
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'metadata' => ['phone_number_id' => $channel->external_id],
                    'contacts' => [['wa_id' => $from, 'profile' => ['name' => 'Webhook Customer']]],
                    'messages' => [[
                        'id' => $messageId,
                        'from' => $from,
                        'timestamp' => (string) now()->timestamp,
                        'type' => 'text',
                        'text' => ['body' => $body],
                    ]],
                ],
            ]],
        ];
    }

    protected function statusPayload(string $status, array $errors = []): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => $this->channel->waba_id,
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => ['phone_number_id' => $this->channel->external_id],
                        'statuses' => [array_filter([
                            'id' => 'wamid.status.target',
                            'status' => $status,
                            'timestamp' => (string) now()->timestamp,
                            'recipient_id' => '8801812345678',
                            'errors' => $errors ?: null,
                        ])],
                    ],
                ]],
            ]],
        ];
    }

    protected function postWebhook(array $payload)
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

        return $this->call(
            'POST',
            '/webhooks/meta',
            [],
            [],
            [],
            $this->transformHeadersToServerVars([
                'Content-Type' => 'application/json',
                'X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $body, 'shared-app-secret'),
            ]),
            $body,
        );
    }
}
