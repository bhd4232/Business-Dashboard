<?php

namespace Tests\Feature;

use App\Filament\Resources\ConversationChannels\Pages\CreateConversationChannel;
use App\Filament\Resources\ConversationChannels\Pages\EditConversationChannel;
use App\Models\Company;
use App\Models\ConversationChannel;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConversationChannelResourceTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'Channel Co',
            'slug' => 'channel-co',
            'invoice_prefix' => 'CH',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        $this->actingAs(User::factory()->create(['role' => 'super_admin', 'is_active' => true]));
    }

    public function test_channel_saves_into_the_active_company(): void
    {
        app(CompanyContext::class)->set($this->company);

        Livewire::test(CreateConversationChannel::class)
            ->fillForm([
                'provider' => 'whatsapp',
                'display_name' => 'Main WABA',
                'external_id' => '111222333444555',
                'waba_id' => '555444333222111',
                'access_token' => 'EAAG-token',
                'app_secret' => 'secret',
                'verify_token' => 'verify-me',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $channel = ConversationChannel::query()->withoutGlobalScopes()->where('external_id', '111222333444555')->first();
        $this->assertNotNull($channel);
        $this->assertSame($this->company->getKey(), $channel->company_id);
    }

    public function test_all_companies_mode_requires_an_explicit_company_and_saves_into_it(): void
    {
        app(CompanyContext::class)->all();

        Livewire::test(CreateConversationChannel::class)
            ->fillForm([
                'provider' => 'whatsapp',
                'display_name' => 'No Company Chosen',
                'external_id' => '900000000000001',
            ])
            ->call('create')
            ->assertHasFormErrors(['company_id' => 'required']);

        Livewire::test(CreateConversationChannel::class)
            ->fillForm([
                'company_id' => $this->company->getKey(),
                'provider' => 'whatsapp',
                'display_name' => 'Explicit Company',
                'external_id' => '900000000000002',
                'waba_id' => '900000000000003',
                'access_token' => 'permanent-token',
                'app_secret' => 'app-secret',
                'verify_token' => 'verify-token',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $channel = ConversationChannel::query()->withoutGlobalScopes()->where('external_id', '900000000000002')->first();
        $this->assertNotNull($channel);
        $this->assertSame($this->company->getKey(), $channel->company_id);
    }

    public function test_duplicate_external_id_shows_validation_error_instead_of_crashing(): void
    {
        app(CompanyContext::class)->set($this->company);

        ConversationChannel::query()->create([
            'provider' => 'whatsapp',
            'external_id' => '777888999000111',
            'display_name' => 'Existing',
        ]);

        Livewire::test(CreateConversationChannel::class)
            ->fillForm([
                'provider' => 'whatsapp',
                'display_name' => 'Duplicate',
                'external_id' => '777888999000111',
            ])
            ->call('create')
            ->assertHasFormErrors(['external_id' => 'unique']);

        // Same external_id under the other provider is allowed.
        Livewire::test(CreateConversationChannel::class)
            ->fillForm([
                'provider' => 'messenger',
                'display_name' => 'Messenger Page',
                'external_id' => '777888999000111',
                'access_token' => 'page-token',
                'app_secret' => 'messenger-app-secret',
                'verify_token' => 'messenger-verify-token',
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_active_channel_requires_complete_meta_setup_but_inactive_draft_does_not(): void
    {
        app(CompanyContext::class)->set($this->company);

        Livewire::test(CreateConversationChannel::class)
            ->fillForm([
                'provider' => 'whatsapp',
                'display_name' => 'Incomplete Active Channel',
                'external_id' => '121212121212121',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'waba_id' => 'required',
                'access_token' => 'required',
                'app_secret' => 'required',
                'verify_token' => 'required',
            ]);

        Livewire::test(CreateConversationChannel::class)
            ->fillForm([
                'provider' => 'whatsapp',
                'display_name' => 'Inactive Draft Channel',
                'external_id' => '131313131313131',
                'is_active' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_editing_with_blank_secret_fields_preserves_encrypted_credentials(): void
    {
        app(CompanyContext::class)->set($this->company);

        $channel = ConversationChannel::query()->create([
            'provider' => 'whatsapp',
            'external_id' => '222333444555666',
            'waba_id' => '999888777',
            'display_name' => 'Secure Channel',
            'access_token' => 'permanent-secret-token',
            'app_secret' => 'meta-app-secret',
            'verify_token' => 'verify-me',
        ]);

        Livewire::test(EditConversationChannel::class, ['record' => $channel->getRouteKey()])
            ->assertFormSet([
                'access_token' => null,
                'app_secret' => null,
                'verify_token' => null,
            ])
            ->fillForm([
                'display_name' => 'Updated Secure Channel',
                'access_token' => '',
                'app_secret' => '',
                'verify_token' => '',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $channel->refresh();
        $this->assertSame('Updated Secure Channel', $channel->display_name);
        $this->assertSame('permanent-secret-token', $channel->access_token);
        $this->assertSame('meta-app-secret', $channel->app_secret);
        $this->assertSame('verify-me', $channel->verify_token);
    }

    public function test_existing_channel_cannot_be_reassigned_to_another_company(): void
    {
        app(CompanyContext::class)->set($this->company);
        $channel = ConversationChannel::query()->create([
            'provider' => 'messenger',
            'external_id' => 'page-tenant-locked',
            'display_name' => 'Tenant Locked Page',
            'is_active' => false,
        ]);
        $otherCompany = Company::query()->create([
            'name' => 'Other Channel Co',
            'slug' => 'other-channel-co',
            'invoice_prefix' => 'OC',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        app(CompanyContext::class)->all();

        Livewire::test(EditConversationChannel::class, ['record' => $channel->getRouteKey()])
            ->assertFormFieldDisabled('company_id')
            ->fillForm(['company_id' => $otherCompany->getKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($this->company->getKey(), $channel->fresh()->company_id);
    }

    public function test_channel_diagnostics_only_confirm_inbound_after_a_real_message_and_recover_after_success(): void
    {
        app(CompanyContext::class)->set($this->company);
        $channel = ConversationChannel::query()->create([
            'provider' => 'whatsapp',
            'external_id' => 'diagnostic-phone-id',
            'waba_id' => 'diagnostic-waba-id',
            'display_name' => 'Diagnostic Channel',
            'is_active' => true,
        ]);

        $channel->markWebhookVerified();
        $channel->markWebhookSubscribed();
        $this->assertSame('Configured', $channel->fresh()->diagnosticStatus());

        $channel->recordDiagnosticError('Temporary Meta failure', 'webhook');
        $this->assertSame('Needs attention', $channel->fresh()->diagnosticStatus());

        $channel->markInboundReceived();
        $this->assertSame('Inbound confirmed', $channel->fresh()->diagnosticStatus());

        $channel->forceFill(['is_active' => false])->save();
        $this->assertSame('Inactive', $channel->fresh()->diagnosticStatus());
    }

    public function test_changing_channel_identity_resets_identity_bound_diagnostics(): void
    {
        app(CompanyContext::class)->set($this->company);
        $channel = ConversationChannel::query()->create([
            'provider' => 'whatsapp',
            'external_id' => 'old-phone-id',
            'waba_id' => 'old-waba-id',
            'display_name' => 'Changing Identity',
            'is_active' => true,
            'webhook_verified_at' => now(),
            'webhook_subscribed_at' => now(),
            'last_webhook_at' => now(),
            'last_inbound_at' => now(),
            'last_outbound_at' => now(),
            'last_health_at' => now(),
            'last_error_at' => now(),
            'last_error' => 'Old identity error',
        ]);

        $channel->forceFill([
            'external_id' => 'new-phone-id',
            'waba_id' => 'new-waba-id',
        ])->save();

        $channel->refresh();
        $this->assertNull($channel->webhook_subscribed_at);
        $this->assertNull($channel->last_webhook_at);
        $this->assertNull($channel->last_inbound_at);
        $this->assertNull($channel->last_outbound_at);
        $this->assertNull($channel->last_health_at);
        $this->assertNull($channel->last_error);
        $this->assertSame('Subscribe app', $channel->diagnosticStatus());
    }
}
