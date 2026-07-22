<?php

namespace Tests\Feature;

use App\Jobs\AiAutoReplyJob;
use App\Models\Company;
use App\Models\CompanyFaq;
use App\Models\Conversation;
use App\Models\ConversationChannel;
use App\Models\ConversationMessage;
use App\Models\Product;
use App\Models\User;
use App\Services\CompanyContext;
use App\Services\Crm\AiReplyService;
use App\Services\Crm\AiSettingsService;
use App\Services\Crm\ConversationMessengerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAutoReplyTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Product $product;

    protected ConversationChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'AI Co',
            'slug' => 'ai-co',
            'invoice_prefix' => 'AI',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        app(CompanyContext::class)->set($this->company);

        app(AiSettingsService::class)->save($this->company, [
            'enabled' => true,
            'provider' => 'anthropic',
            'model' => 'claude-test',
            'confidence_threshold' => 0.75,
            'max_consecutive_ai_replies' => 3,
            'api_key' => 'test-key',
        ]);
        $this->company->refresh();

        $this->product = Product::query()->create([
            'name' => 'Smart Bulb',
            'sku' => 'AI-BULB-001',
            'price' => 420,
            'sale_price' => 420,
            'cost_price' => 250,
            'stock' => 15,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);

        $this->channel = ConversationChannel::query()->create([
            'provider' => 'whatsapp',
            'external_id' => 'ai-phone-1',
            'display_name' => 'AI Co WhatsApp',
            'access_token' => 'wa-token',
            'is_active' => true,
        ]);
    }

    /** Fakes the WhatsApp Graph API send endpoint plus an Anthropic sequence. */
    protected function fakeLlm(array ...$anthropicResponses): void
    {
        $sequence = Http::sequence();

        foreach ($anthropicResponses as $response) {
            $sequence->push($response);
        }

        Http::fake([
            'api.anthropic.com/*' => $sequence,
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out.'.uniqid()]]]),
        ]);
    }

    protected function conversation(string $incomingBody, array $attributes = []): Conversation
    {
        $conversation = Conversation::query()->create(array_merge([
            'provider' => 'whatsapp',
            'channel_id' => $this->channel->getKey(),
            'external_contact_id' => '880170'.random_int(1000000, 9999999),
            'contact_name' => 'AI Customer',
            'contact_phone' => '8801700000000',
            'status' => 'open',
        ], $attributes));

        ConversationMessage::query()->create([
            'conversation_id' => $conversation->getKey(),
            'direction' => 'incoming',
            'type' => 'text',
            'body' => $incomingBody,
            'external_message_id' => 'wamid.'.uniqid(),
            'sent_at' => now(),
        ]);

        return $conversation->fresh();
    }

    protected function anthropicToolUse(string $name, array $input): array
    {
        return [
            'content' => [['type' => 'tool_use', 'id' => 'tu_'.uniqid(), 'name' => $name, 'input' => $input]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
        ];
    }

    public function test_product_query_gets_grounded_reply_with_real_price(): void
    {
        $this->fakeLlm(
            $this->anthropicToolUse('lookup_product', ['name' => 'Smart Bulb']),
            $this->anthropicToolUse('submit_reply', [
                'answer' => 'Smart Bulb-এর দাম ৪২০ টাকা, স্টকে আছে।',
                'used_product_ids' => [$this->product->getKey()],
                'confidence' => 0.95,
                'needs_human' => false,
            ]),
        );

        $conversation = $this->conversation('Smart Bulb এর দাম কত?');
        app(AiReplyService::class)->maybeReply($conversation);

        $reply = $conversation->messages()->where('direction', 'outgoing')->first();
        $this->assertNotNull($reply);
        $this->assertSame('ai', $reply->generated_by);
        $this->assertStringContainsString('৪২০', $reply->body);
        $this->assertStringContainsString('অ্যাসিস্ট্যান্ট', $reply->body); // transparency prefix
        $this->assertSame('open', $conversation->fresh()->status);
    }

    public function test_complaint_message_is_never_answered_by_ai(): void
    {
        Http::fake();

        $conversation = $this->conversation('আমি রিফান্ড চাই, প্রোডাক্ট ভাঙা ছিল');
        app(AiReplyService::class)->maybeReply($conversation);

        Http::assertNothingSent();
        $this->assertSame('pending', $conversation->fresh()->status);
        $this->assertSame(0, $conversation->messages()->where('direction', 'outgoing')->count());
    }

    public function test_low_confidence_reply_is_blocked_and_conversation_goes_pending(): void
    {
        $this->fakeLlm($this->anthropicToolUse('submit_reply', [
            'answer' => 'সম্ভবত স্টকে আছে।',
            'confidence' => 0.4,
            'needs_human' => false,
        ]));

        $conversation = $this->conversation('XYZ প্রোডাক্ট আছে?');
        app(AiReplyService::class)->maybeReply($conversation);

        $this->assertSame('pending', $conversation->fresh()->status);
        $this->assertSame(0, $conversation->messages()->where('direction', 'outgoing')->count());
    }

    public function test_ungrounded_price_in_answer_is_blocked_never_echo(): void
    {
        // Customer claims a fake price; the model echoes 500 even though the
        // tool returned 420 — the code-level cross-check must block it.
        $this->fakeLlm(
            $this->anthropicToolUse('lookup_product', ['name' => 'Smart Bulb']),
            $this->anthropicToolUse('submit_reply', [
                'answer' => 'জি, আপনি ঠিকই বলেছেন — দাম ৫০০ টাকা।',
                'used_product_ids' => [$this->product->getKey()],
                'confidence' => 0.9,
                'needs_human' => false,
            ]),
        );

        $conversation = $this->conversation('আপনি তো বলেছিলেন Smart Bulb ৫০০ টাকা');
        app(AiReplyService::class)->maybeReply($conversation);

        $this->assertSame('pending', $conversation->fresh()->status);
        $this->assertSame(0, $conversation->messages()->where('direction', 'outgoing')->count());
    }

    public function test_escalate_tool_hands_off_without_reply(): void
    {
        $this->fakeLlm($this->anthropicToolUse('escalate_to_human', ['reason' => 'Custom bulk pricing request']));

        $conversation = $this->conversation('১০০ পিস নিলে স্পেশাল রেট হবে?');
        app(AiReplyService::class)->maybeReply($conversation);

        $this->assertSame('pending', $conversation->fresh()->status);
        $this->assertSame(0, $conversation->messages()->where('direction', 'outgoing')->count());
    }

    public function test_faq_keyword_match_replies_without_llm_call(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.faq']]]),
        ]);

        CompanyFaq::query()->create([
            'question' => 'ডেলিভারি কত দিনে হয়?',
            'answer' => 'ঢাকার ভিতরে ১-২ দিন, বাইরে ২-৪ দিনে ডেলিভারি হয়।',
            'keywords' => 'ডেলিভারি, delivery',
            'is_active' => true,
        ]);

        $conversation = $this->conversation('ডেলিভারি কত দিন লাগবে?');
        app(AiReplyService::class)->maybeReply($conversation);

        // FAQ shortcut skips the LLM entirely (only the WhatsApp send happens).
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'anthropic'));
        $reply = $conversation->messages()->where('direction', 'outgoing')->first();
        $this->assertNotNull($reply);
        $this->assertSame('ai', $reply->generated_by);
        $this->assertStringContainsString('১-২ দিন', $reply->body);
    }

    public function test_auto_reply_job_processes_each_source_message_only_once(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.idempotent.reply']]]),
        ]);
        CompanyFaq::query()->create([
            'question' => 'Delivery time',
            'answer' => 'Delivery takes two to four days.',
            'keywords' => 'delivery',
            'is_active' => true,
        ]);
        $conversation = $this->conversation('How long does delivery take?');
        $source = $conversation->messages()->where('direction', 'incoming')->sole();
        $job = new AiAutoReplyJob($conversation->getKey(), $source->getKey());

        $job->handle(app(CompanyContext::class), app(AiReplyService::class));
        $job->handle(app(CompanyContext::class), app(AiReplyService::class));

        $reply = $conversation->messages()->where('generated_by', 'ai')->sole();
        $this->assertSame($source->getKey(), (int) data_get($reply->ai_meta, 'source_message_id'));
        $this->assertNotNull(data_get($source->refresh()->raw_payload, '_local.ai_processed_at'));
        $this->assertSame(1, $conversation->messages()->where('generated_by', 'ai')->count());
        Http::assertSentCount(1);
    }

    public function test_human_handled_conversation_skips_ai(): void
    {
        Http::fake();

        $conversation = $this->conversation('দাম কত?', ['human_handled_until' => now()->addHours(5)]);
        app(AiReplyService::class)->maybeReply($conversation);

        Http::assertNothingSent();
        $this->assertSame(0, $conversation->messages()->where('direction', 'outgoing')->count());
    }

    public function test_human_reply_sets_human_handled_until_and_generated_by_human(): void
    {
        Http::fake();

        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $conversation = $this->conversation('হ্যালো', ['provider' => 'manual', 'status' => 'pending']);

        $message = app(ConversationMessengerService::class)->send($conversation, 'আসসালামু আলাইকুম!', $user, 'note');

        $this->assertSame('human', $message->generated_by);
        $conversation = $conversation->fresh();
        $this->assertNotNull($conversation->human_handled_until);
        $this->assertTrue($conversation->human_handled_until->isFuture());
        $this->assertSame('open', $conversation->status);
    }

    public function test_ctwa_ad_conversation_gets_72_hour_window(): void
    {
        $adConversation = $this->conversation('বিজ্ঞাপন দেখে এলাম', ['entry_point' => 'ctwa_ad']);
        $normalConversation = $this->conversation('সাধারণ মেসেজ');

        $adConversation->messages()->update(['sent_at' => now()->subHours(48)]);
        $normalConversation->messages()->update(['sent_at' => now()->subHours(48)]);

        $this->assertTrue($adConversation->fresh()->withinReplyWindow());
        $this->assertFalse($normalConversation->fresh()->withinReplyWindow());

        $adConversation->messages()->update(['sent_at' => now()->subHours(80)]);
        $this->assertFalse($adConversation->fresh()->withinReplyWindow());
    }

    public function test_consecutive_ai_reply_limit_triggers_handoff(): void
    {
        Http::fake();

        $conversation = $this->conversation('আরেকটা প্রশ্ন');

        foreach (range(1, 3) as $i) {
            ConversationMessage::query()->create([
                'conversation_id' => $conversation->getKey(),
                'direction' => 'outgoing',
                'type' => 'text',
                'body' => "AI reply {$i}",
                'generated_by' => 'ai',
                'sent_at' => now()->addSeconds($i),
            ]);
        }

        app(AiReplyService::class)->maybeReply($conversation->fresh());

        Http::assertNothingSent();
        $this->assertSame('pending', $conversation->fresh()->status);
    }

    public function test_ai_disabled_company_never_calls_llm(): void
    {
        Http::fake();

        app(AiSettingsService::class)->save($this->company, ['enabled' => false, 'api_key' => 'test-key']);
        $this->company->refresh();

        $conversation = $this->conversation('দাম জানতে চাই');
        $conversation->setRelation('company', $this->company);
        app(AiReplyService::class)->maybeReply($conversation);

        Http::assertNothingSent();
        $this->assertSame(0, $conversation->messages()->where('direction', 'outgoing')->count());
    }
}
