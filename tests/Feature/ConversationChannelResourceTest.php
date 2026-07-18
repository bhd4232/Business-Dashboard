<?php

namespace Tests\Feature;

use App\Filament\Resources\ConversationChannels\Pages\CreateConversationChannel;
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
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }
}
