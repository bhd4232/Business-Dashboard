<?php

namespace Tests\Feature;

use App\Filament\Pages\CoolifyDeploymentSettings;
use App\Models\User;
use App\Support\CoolifySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CoolifyDeploymentSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_a_super_admin_can_access_the_settings_page(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);

        $this->actingAs($superAdmin)
            ->get('/admin/settings/coolify-deployment-settings')
            ->assertOk();

        $this->actingAs($manager)
            ->get('/admin/settings/coolify-deployment-settings')
            ->assertForbidden();
    }

    public function test_saving_stores_credentials_with_the_token_encrypted(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        Livewire::actingAs($superAdmin)
            ->test(CoolifyDeploymentSettings::class)
            ->set('settings.enabled', true)
            ->set('settings.base_url', 'https://coolify.example.com/')
            ->set('settings.api_token', 'fake-token-123')
            ->set('settings.application_uuid', 'jc0k48s')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(CoolifySettings::enabled());
        // Trailing slash trimmed so CoolifyDeploymentService can safely append /api/v1.
        $this->assertSame('https://coolify.example.com', CoolifySettings::baseUrl());
        $this->assertSame('fake-token-123', CoolifySettings::apiToken());
        $this->assertSame('jc0k48s', CoolifySettings::applicationUuid());
        $this->assertTrue(CoolifySettings::isConfigured());

        $this->assertDatabaseHas('app_settings', ['key' => 'coolify.api_token', 'is_encrypted' => true]);

        // Never round-tripped to the browser after saving.
        $refreshed = Livewire::actingAs($superAdmin)->test(CoolifyDeploymentSettings::class);
        $refreshed->assertSet('settings.api_token', '')
            ->assertSet('settings.has_api_token', true);
    }

    public function test_leaving_the_token_blank_keeps_the_previously_saved_value(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        CoolifySettings::save([
            'base_url' => 'https://coolify.example.com',
            'api_token' => 'original-token',
            'application_uuid' => 'jc0k48s',
        ]);

        Livewire::actingAs($superAdmin)
            ->test(CoolifyDeploymentSettings::class)
            ->set('settings.application_uuid', 'updated-uuid-only')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('updated-uuid-only', CoolifySettings::applicationUuid());
        $this->assertSame('original-token', CoolifySettings::apiToken());
    }
}
