<?php

namespace Tests\Feature;

use App\Filament\Pages\Inbox;
use App\Filament\Pages\SalesAutomation;
use App\Filament\Widgets\CrmSalesMetrics;
use App\Jobs\AiAutoReplyJob;
use App\Models\ChatOrderLink;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\ConversationChannel;
use App\Models\ConversationMessage;
use App\Models\CrmAiRun;
use App\Models\Lead;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CompanyContext;
use App\Services\Crm\AiReplyService;
use App\Services\Crm\AiSettingsService;
use App\Services\Crm\SalesCartService;
use App\Services\Crm\SalesFollowUpService;
use App\Services\Crm\SalesQualificationService;
use App\Services\Crm\SalesReplyRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CrmSalesAutomationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Conversation $conversation;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->company = Company::query()->create(['name' => 'Sales Co', 'slug' => 'sales-automation', 'invoice_prefix' => 'SA', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true]);
        app(CompanyContext::class)->set($this->company);
        $this->settings();
        $channel = ConversationChannel::query()->create(['provider' => 'whatsapp', 'external_id' => 'sales-channel', 'display_name' => 'Sales', 'access_token' => 'test', 'is_active' => true]);
        $this->conversation = Conversation::query()->create(['channel_id' => $channel->id, 'provider' => 'whatsapp', 'external_contact_id' => '8801700000000', 'contact_phone' => '01700000000', 'contact_name' => 'Customer', 'status' => 'open', 'ai_enabled' => true]);
        $this->incoming('ঢাকায় দুইটি নিতে চাই');
        $this->product = Product::query()->create(['name' => 'Lamp', 'sku' => 'AUTO-LAMP', 'price' => 400, 'sale_price' => 400, 'cost_price' => 100, 'stock' => 10, 'unit' => 'pcs', 'is_active' => true, 'status' => 'available']);
    }

    private function settings(array $extra = []): void
    {
        app(AiSettingsService::class)->save($this->company, 'messaging', [...AiSettingsService::DEFAULTS, 'enabled' => true, 'api_key' => 'test', ...$extra]);
        $this->company->refresh();
        if (isset($this->conversation)) {
            $this->conversation->unsetRelation('company');
        }
    }

    private function incoming(string $body): ConversationMessage
    {
        return $this->conversation->messages()->create(['direction' => 'incoming', 'type' => 'text', 'body' => $body, 'sent_at' => now()]);
    }

    private function response(array $input): array
    {
        return ['content' => [['type' => 'tool_use', 'id' => 'reply-1', 'name' => 'submit_reply', 'input' => ['confidence' => 0.95, 'needs_human' => false, ...$input]]], 'usage' => ['input_tokens' => 20, 'output_tokens' => 20]];
    }

    private function link(): ChatOrderLink
    {
        return ChatOrderLink::query()->create(['conversation_id' => $this->conversation->id, 'prefill' => ['items' => []]]);
    }

    public function test_banglish_price_reply_has_no_unsolicited_stock_or_question(): void
    {
        $this->incoming('ei lamp er daam koto?');
        $key = 'product:'.$this->product->id;
        $reply = app(SalesReplyRenderer::class)->render($this->conversation, ['reply_keys' => [$key], 'product_detail' => 'price', 'prompt_key' => 'budget'], [['result' => ['reply_options' => [$key]]]]);
        $this->assertSame('Lamp — 400 taka', $reply);
    }

    public function test_native_bengali_stock_reply_does_not_add_price(): void
    {
        $this->incoming('ল্যাম্পটা আছে?');
        $key = 'product:'.$this->product->id;
        $reply = app(SalesReplyRenderer::class)->render($this->conversation, ['reply_keys' => [$key], 'product_detail' => 'stock'], [['result' => ['reply_options' => [$key]]]]);
        $this->assertSame('Lamp — স্টকে আছে।', $reply);
    }

    public function test_banglish_small_talk_is_short_and_language_can_be_requested_explicitly(): void
    {
        $this->incoming('tumi kmn aso?');
        $renderer = app(SalesReplyRenderer::class);
        $this->assertSame('Apnake shahajjo korte prostut achi.', $renderer->render($this->conversation, ['prompt_key' => 'wellbeing'], []));
        $this->assertSame('ধন্যবাদ।', $renderer->render($this->conversation, ['prompt_key' => 'thanks', 'language' => 'bn'], []));
        $this->assertSame('Thank you.', $renderer->render($this->conversation, ['prompt_key' => 'thanks', 'language' => 'en'], []));
    }

    public function test_llm_receives_banglish_rules_and_sent_reply_has_no_introduction(): void
    {
        $this->settings(['sales_guidelines' => 'CUSTOM_GUIDELINE: সহজ বাংলায় সংক্ষিপ্ত উত্তর দিন।']);
        $this->incoming('tumi kmn aso?');
        Http::fake(['api.anthropic.com/*' => Http::response($this->response(['reply_keys' => [], 'prompt_key' => 'wellbeing', 'language' => 'banglish'])), 'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'banglish-1']]])]);
        app(AiReplyService::class)->maybeReply($this->conversation);
        $this->assertSame('Apnake shahajjo korte prostut achi.', $this->conversation->messages()->where('direction', 'outgoing')->sole()->body);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'anthropic') && str_contains(json_encode($request['system']), 'tumi kmn aso') && str_contains(json_encode($request['system']), 'No unsolicited introduction') && str_contains(json_encode($request['system']), 'CUSTOM_GUIDELINE') && str_contains(json_encode($request['system']), 'NEVER catalog facts'));
    }

    public function test_handoff_stays_disabled_for_the_next_customer_message(): void
    {
        Http::fake();
        $service = app(AiReplyService::class);
        $service->escalate($this->conversation, 'Customer requested staff.');
        $this->incoming('Lamp price?');
        $service->maybeReply($this->conversation->fresh());
        Http::assertNothingSent();
        $this->assertFalse($this->conversation->fresh()->ai_enabled);
    }

    public function test_internal_notes_and_failed_outbound_are_not_submitted_to_the_llm(): void
    {
        $this->conversation->messages()->create(['direction' => 'outgoing', 'type' => 'note', 'body' => 'PRIVATE STAFF MARGIN', 'delivery_status' => 'internal', 'sent_at' => now()]);
        $this->conversation->messages()->create(['direction' => 'outgoing', 'type' => 'text', 'body' => 'FAILED PRICE PROMISE', 'delivery_status' => 'failed', 'sent_at' => now()]);
        Http::fake(['api.anthropic.com/*' => Http::response($this->response(['reply_keys' => [], 'prompt_key' => 'product'])), 'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'out-1']]])]);
        app(AiReplyService::class)->maybeReply($this->conversation);
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'anthropic')) {
                return false;
            }
            $context = json_encode($request['messages']);
            $this->assertStringNotContainsString('PRIVATE STAFF', $context);
            $this->assertStringNotContainsString('FAILED PRICE', $context);

            return true;
        });
        $this->assertSame('sent', CrmAiRun::query()->sole()->status);
    }

    public function test_staff_takeover_during_inference_prevents_the_send(): void
    {
        Http::fake(['api.anthropic.com/*' => function () {
            $this->conversation->update(['human_handled_until' => now()->addDay()]);

            return Http::response($this->response(['reply_keys' => [], 'prompt_key' => 'product']));
        }]);
        app(AiReplyService::class)->maybeReply($this->conversation);
        Http::assertSentCount(1);
        $this->assertSame(0, $this->conversation->messages()->where('direction', 'outgoing')->count());
    }

    public function test_older_inbound_job_is_coalesced_without_an_ai_call(): void
    {
        Http::fake();
        $source = $this->conversation->messages()->first();
        $this->incoming('আরেকটি প্রশ্ন');
        (new AiAutoReplyJob($this->conversation->id, $source->id))->handle(app(CompanyContext::class), app(AiReplyService::class));
        Http::assertNothingSent();
        $this->assertNotNull(data_get($source->fresh()->raw_payload, '_local.ai_processed_at'));
    }

    public function test_model_prose_cannot_bypass_price_validation(): void
    {
        foreach (['BDT 999', 'নয়শ টাকা', 'Lamp costs 999', 'https://untrusted.example/checkout'] as $answer) {
            try {
                app(SalesReplyRenderer::class)->render($this->conversation, ['answer' => $answer], []);
                $this->fail('Unsupported model prose was accepted.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_catalog_claims_are_reloaded_before_rendering(): void
    {
        $key = 'product:'.$this->product->id;
        $this->product->update(['sale_price' => 550, 'price' => 550]);
        $answer = app(SalesReplyRenderer::class)->render($this->conversation, ['reply_keys' => [$key]], [['result' => ['reply_options' => [$key]]]]);
        $this->assertStringContainsString('৫৫০', $answer);
        $this->assertStringNotContainsString('৪০০', $answer);
    }

    public function test_unlooked_up_product_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(SalesReplyRenderer::class)->render($this->conversation, ['reply_keys' => ['product:'.$this->product->id]], []);
    }

    public function test_variant_checkout_uses_variant_price_and_requires_a_selection(): void
    {
        $this->product->update(['has_variants' => true]);
        $variant = ProductVariant::query()->create(['product_id' => $this->product->id, 'sku' => 'LAMP-BLUE', 'options' => ['Color' => 'Blue'], 'sale_price' => 650, 'stock' => 5, 'is_active' => true]);
        $item = app(SalesCartService::class)->item($this->conversation, ['product_id' => $this->product->id, 'variant_id' => $variant->id, 'quantity' => 2]);
        $this->assertSame($variant->id, $item['product_variant_id']);
        $this->assertSame(650.0, $item['unit_price']);
        $this->expectException(ValidationException::class);
        app(SalesCartService::class)->item($this->conversation, ['product_id' => $this->product->id]);
    }

    public function test_insufficient_stock_cannot_create_a_cart_item(): void
    {
        $this->expectException(ValidationException::class);
        app(SalesCartService::class)->item($this->conversation, ['product_id' => $this->product->id, 'quantity' => 11]);
    }

    public function test_qualification_retains_customer_evidence_and_rejects_inference(): void
    {
        $source = $this->conversation->messages()->first();
        $service = app(SalesQualificationService::class);
        $saved = $service->update($this->conversation, ['field' => 'delivery_area', 'value' => 'ঢাকা', 'evidence_message_id' => $source->id, 'evidence_quote' => $source->body]);
        $this->assertTrue($saved['saved']);
        $lead = $this->conversation->fresh()->lead;
        $this->assertSame($source->id, data_get($lead->qualification, 'delivery_area.evidence_message_id'));
        $denied = $service->update($this->conversation, ['field' => 'budget', 'value' => '5000', 'evidence_message_id' => $source->id, 'evidence_quote' => $source->body]);
        $this->assertArrayHasKey('error', $denied);
        $this->assertArrayNotHasKey('budget', $lead->fresh()->qualification);
    }

    public function test_follow_up_is_cancelled_after_new_inbound(): void
    {
        $this->settings(['sales_follow_ups_enabled' => true, 'follow_up_template' => 'sales_followup']);
        $service = app(SalesFollowUpService::class);
        $followUp = $service->schedule($this->conversation, $this->link());
        $followUp->update(['due_at' => now()->subMinute()]);
        $this->incoming('আর লাগবে না');
        $service->process($followUp);
        $this->assertSame('cancelled', $followUp->fresh()->status);
        $this->assertStringContainsString('replied', $followUp->fresh()->reason);
    }

    public function test_follow_up_rechecks_opt_out_using_normalized_phone(): void
    {
        $this->settings(['sales_follow_ups_enabled' => true, 'follow_up_template' => 'sales_followup']);
        $service = app(SalesFollowUpService::class);
        $followUp = $service->schedule($this->conversation, $this->link());
        $followUp->update(['due_at' => now()->subMinute()]);
        Lead::query()->create(['name' => 'Opted out', 'phone' => '+8801700000000', 'opted_out_at' => now()]);
        $service->process($followUp);
        $this->assertSame('cancelled', $followUp->fresh()->status);
        $this->assertStringContainsString('opted out', $followUp->fresh()->reason);
    }

    public function test_follow_up_template_is_sent_once_and_archived(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'follow-up-1']]])]);
        $this->settings(['sales_follow_ups_enabled' => true, 'follow_up_template' => 'sales_followup']);
        $service = app(SalesFollowUpService::class);
        $link = $this->link();
        $followUp = $service->schedule($this->conversation, $link);
        $this->assertSame($followUp->id, $service->schedule($this->conversation, $link)->id);
        $followUp->update(['due_at' => now()->subMinute()]);
        $service->process($followUp);
        $service->process($followUp);
        Http::assertSentCount(1);
        $this->assertSame('sent', $followUp->fresh()->status);
        $this->assertSame('follow-up-1', $this->conversation->messages()->where('direction', 'outgoing')->sole()->external_message_id);
    }

    public function test_review_mode_records_a_draft_without_sending(): void
    {
        $this->settings(['review_mode' => true]);
        Http::fake(['api.anthropic.com/*' => Http::response($this->response(['reply_keys' => [], 'prompt_key' => 'quantity']))]);
        app(AiReplyService::class)->maybeReply($this->conversation);
        Http::assertSentCount(1);
        $this->assertSame('suggested', CrmAiRun::query()->sole()->status);
        $this->assertSame(0, $this->conversation->messages()->where('direction', 'outgoing')->count());
    }

    public function test_daily_budget_blocks_before_http_and_hands_off(): void
    {
        $this->settings(['daily_budget_usd' => 0.001, 'input_cost_per_million' => 10, 'output_cost_per_million' => 10]);
        Http::fake();
        app(AiReplyService::class)->maybeReply($this->conversation);
        Http::assertNothingSent();
        $this->assertSame('budget_blocked', CrmAiRun::query()->sole()->status);
        $this->assertFalse($this->conversation->fresh()->ai_enabled);
    }

    public function test_native_sales_automation_page_renders_for_authorized_staff(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin', 'is_active' => true]));
        Livewire::test(SalesAutomation::class)->assertSuccessful();
        Livewire::test(CrmSalesMetrics::class)->assertSuccessful()->assertSee('Completed CRM/chat orders');
    }

    public function test_inbox_presence_renews_before_thirty_second_expiry(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin', 'is_active' => true]));
        Cache::forget('crm:human-present:'.$this->conversation->id);
        Livewire::test(Inbox::class)->set('selectedConversationId', $this->conversation->id)->set('humanPresentMarkedAt', microtime(true) - 16)->call('refreshInbox')->assertHasNoErrors();
        $this->assertTrue($this->conversation->hasHumanPresent());
    }
}
