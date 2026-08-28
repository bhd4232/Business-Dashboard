<?php

namespace Tests\Feature;

use App\Filament\Resources\Broadcasts\Pages\CreateBroadcast;
use App\Jobs\SendBroadcastJob;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Company;
use App\Models\ConversationChannel;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\StorefrontSetting;
use App\Models\User;
use App\Services\CompanyContext;
use App\Services\Crm\BroadcastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class BroadcastTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected ConversationChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'Broadcast Co',
            'slug' => 'broadcast-co',
            'invoice_prefix' => 'BC',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        app(CompanyContext::class)->set($this->company);

        $this->channel = ConversationChannel::query()->create([
            'provider' => 'whatsapp',
            'external_id' => 'broadcast-phone-id',
            'waba_id' => 'broadcast-waba-id',
            'display_name' => 'Broadcast WhatsApp',
            'access_token' => 'broadcast-token',
            'app_secret' => 'broadcast-secret',
            'verify_token' => 'broadcast-verify',
            'is_active' => true,
        ]);
    }

    public function test_recipient_list_respects_filters_opt_out_and_dedupes_by_phone(): void
    {
        Lead::query()->create(['name' => 'Match', 'phone' => '8801811110001', 'status' => 'new', 'source' => 'facebook']);
        Lead::query()->create(['name' => 'Wrong Status', 'phone' => '8801811110002', 'status' => 'won', 'source' => 'facebook']);
        Lead::query()->create(['name' => 'Wrong Source', 'phone' => '8801811110003', 'status' => 'new', 'source' => 'referral']);
        Lead::query()->create(['name' => 'Opted Out', 'phone' => '8801811110004', 'status' => 'new', 'source' => 'facebook', 'opted_out_at' => now()]);
        Lead::query()->create(['name' => 'No Phone', 'phone' => '', 'status' => 'new', 'source' => 'facebook']);
        // Same phone as the matching lead -- must be counted once, not twice.
        Customer::query()->create(['name' => 'Same Phone Customer', 'phone' => '8801811110001', 'opening_balance' => 0, 'is_active' => true]);
        Customer::query()->create(['name' => 'Unique Customer', 'phone' => '8801811119999', 'opening_balance' => 0, 'is_active' => true]);

        $broadcast = Broadcast::query()->create([
            'name' => 'Filtered Broadcast',
            'audience_type' => 'both',
            'lead_status_filter' => ['new'],
            'lead_source_filter' => ['facebook'],
            'channel' => 'sms',
            'sms_body' => 'Hi {{name}}',
            'status' => 'draft',
        ]);

        $count = app(BroadcastService::class)->buildRecipients($broadcast);

        $this->assertSame(2, $count);
        $this->assertSame(2, $broadcast->fresh()->recipients_count);
        $phones = BroadcastRecipient::query()->where('broadcast_id', $broadcast->getKey())->pluck('phone')->sort()->values()->all();
        $this->assertSame(['8801811110001', '8801811119999'], $phones);
    }

    public function test_rebuilding_recipients_replaces_the_previous_list(): void
    {
        Lead::query()->create(['name' => 'First', 'phone' => '8801811110005', 'status' => 'new', 'source' => 'other']);

        $broadcast = Broadcast::query()->create([
            'name' => 'Rebuildable',
            'audience_type' => 'leads',
            'channel' => 'sms',
            'sms_body' => 'Hi {{name}}',
            'status' => 'draft',
        ]);

        app(BroadcastService::class)->buildRecipients($broadcast);
        $this->assertSame(1, BroadcastRecipient::query()->where('broadcast_id', $broadcast->getKey())->count());

        Lead::query()->create(['name' => 'Second', 'phone' => '8801811110006', 'status' => 'new', 'source' => 'other']);
        app(BroadcastService::class)->buildRecipients($broadcast);

        $this->assertSame(2, BroadcastRecipient::query()->where('broadcast_id', $broadcast->getKey())->count());
    }

    public function test_send_uses_whatsapp_template_and_falls_back_to_sms_on_failure(): void
    {
        StorefrontSetting::query()->create([
            'company_id' => $this->company->getKey(),
            'is_published' => true,
            'new_customer_delivery_advance_enabled' => false,
            'notification_credentials' => [
                'sms_api_url' => 'http://sms.example.test/send?key={api_key}&to={phone}&msg={message}&from={sender_id}',
                'sms_api_key' => 'key',
                'sms_sender_id' => 'BC',
            ],
        ]);
        Lead::query()->create(['name' => 'Whatsapp Ok', 'phone' => '8801811111111', 'status' => 'new']);
        Lead::query()->create(['name' => 'Whatsapp Fails', 'phone' => '8801811112222', 'status' => 'new']);

        $broadcast = Broadcast::query()->create([
            'name' => 'Both Channel Broadcast',
            'audience_type' => 'leads',
            'channel' => 'both',
            'whatsapp_channel_id' => $this->channel->getKey(),
            'whatsapp_template_name' => 'broadcast_template',
            'whatsapp_template_language' => 'bn',
            'sms_body' => 'Hi {{name}}, fallback message',
            'status' => 'queued',
        ]);
        app(BroadcastService::class)->buildRecipients($broadcast);

        Http::fake([
            'graph.facebook.com/*' => Http::sequence()
                ->push(['messages' => [['id' => 'wamid.broadcast.ok']]])
                ->push(['error' => ['message' => 'undeliverable']], 400),
            'sms.example.test/*' => Http::response('OK', 200),
        ]);

        app(BroadcastService::class)->send($broadcast->fresh());

        $broadcast->refresh();
        $this->assertSame('completed', $broadcast->status);
        $this->assertSame(2, $broadcast->sent_count);
        $this->assertSame(0, $broadcast->failed_count);

        $whatsappRecipient = BroadcastRecipient::query()->where('phone', '8801811111111')->first();
        $smsRecipient = BroadcastRecipient::query()->where('phone', '8801811112222')->first();
        $this->assertSame('whatsapp', $whatsappRecipient->channel_used);
        $this->assertSame('sms', $smsRecipient->channel_used);
    }

    public function test_send_marks_recipient_failed_when_every_channel_fails(): void
    {
        Lead::query()->create(['name' => 'No Channel Works', 'phone' => '8801811113333', 'status' => 'new']);

        $broadcast = Broadcast::query()->create([
            'name' => 'Doomed Broadcast',
            'audience_type' => 'leads',
            'channel' => 'whatsapp',
            'whatsapp_channel_id' => $this->channel->getKey(),
            'whatsapp_template_name' => 'broadcast_template',
            'whatsapp_template_language' => 'bn',
            'status' => 'queued',
        ]);
        app(BroadcastService::class)->buildRecipients($broadcast);

        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'fail']], 400)]);

        app(BroadcastService::class)->send($broadcast->fresh());

        $broadcast->refresh();
        $this->assertSame('failed', $broadcast->status);
        $this->assertSame(0, $broadcast->sent_count);
        $this->assertSame(1, $broadcast->failed_count);
        $this->assertSame('failed', BroadcastRecipient::query()->where('phone', '8801811113333')->value('status'));
    }

    public function test_queue_dispatches_the_send_job(): void
    {
        Bus::fake([SendBroadcastJob::class]);
        Lead::query()->create(['name' => 'Queued Lead', 'phone' => '8801811114444', 'status' => 'new']);

        $broadcast = Broadcast::query()->create([
            'name' => 'Queue Me',
            'audience_type' => 'leads',
            'channel' => 'sms',
            'sms_body' => 'Hi {{name}}',
            'status' => 'draft',
        ]);

        app(BroadcastService::class)->queue($broadcast);

        $this->assertSame('queued', $broadcast->fresh()->status);
        Bus::assertDispatched(SendBroadcastJob::class, fn (SendBroadcastJob $job): bool => $job->broadcastId === $broadcast->getKey());
    }

    public function test_creating_a_broadcast_via_the_admin_form_saves_into_the_active_company(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin', 'is_active' => true]));

        Livewire::test(CreateBroadcast::class)
            ->fillForm([
                'name' => 'Form Created Broadcast',
                'audience_type' => 'customers',
                'channel' => 'sms',
                'sms_body' => 'Hi {{name}}',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $broadcast = Broadcast::query()->withoutGlobalScopes()->where('name', 'Form Created Broadcast')->first();
        $this->assertNotNull($broadcast);
        $this->assertSame($this->company->getKey(), $broadcast->company_id);
        $this->assertSame('draft', $broadcast->status);
    }
}
