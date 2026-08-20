<?php

namespace Tests\Feature;

use App\Filament\Pages\PushNotificationSettings;
use App\Models\User;
use App\Support\FirebaseSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PushNotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_a_super_admin_can_access_the_settings_page(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);

        $this->actingAs($superAdmin)
            ->get('/admin/settings/push-notification-settings')
            ->assertOk();

        $this->actingAs($manager)
            ->get('/admin/settings/push-notification-settings')
            ->assertForbidden();
    }

    public function test_saving_stores_web_config_and_service_account_encrypted(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $serviceAccount = json_encode([
            'type' => 'service_account',
            'project_id' => 'zamzam-erp-app',
            'client_email' => 'firebase-adminsdk@zamzam-erp-app.iam.gserviceaccount.com',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nfake\n-----END PRIVATE KEY-----\n",
        ]);

        Livewire::actingAs($superAdmin)
            ->test(PushNotificationSettings::class)
            ->set('settings.enabled', true)
            ->set('settings.api_key', 'AIzaFake')
            ->set('settings.auth_domain', 'zamzam-erp-app.firebaseapp.com')
            ->set('settings.project_id', 'zamzam-erp-app')
            ->set('settings.storage_bucket', 'zamzam-erp-app.firebasestorage.app')
            ->set('settings.messaging_sender_id', '569311109559')
            ->set('settings.app_id', '1:569311109559:web:abc123')
            ->set('settings.vapid_key', 'fake-vapid-key')
            ->set('settings.service_account_json', $serviceAccount)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(FirebaseSettings::enabled());
        $this->assertSame('AIzaFake', FirebaseSettings::webConfig()['apiKey']);
        $this->assertSame('fake-vapid-key', FirebaseSettings::vapidKey());
        $this->assertSame(
            'firebase-adminsdk@zamzam-erp-app.iam.gserviceaccount.com',
            FirebaseSettings::serviceAccountJson()['client_email'],
        );

        // Never round-tripped to the browser after saving.
        $refreshed = Livewire::actingAs($superAdmin)->test(PushNotificationSettings::class);
        $refreshed->assertSet('settings.service_account_json', '')
            ->assertSet('settings.has_service_account', true)
            ->assertSet('settings.vapid_key', '')
            ->assertSet('settings.has_vapid_key', true);
    }

    public function test_an_invalid_service_account_json_is_rejected(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        Livewire::actingAs($superAdmin)
            ->test(PushNotificationSettings::class)
            ->set('settings.service_account_json', '{not valid json')
            ->call('save')
            ->assertHasErrors('settings.service_account_json');

        $this->assertNull(FirebaseSettings::serviceAccountJson());
    }

    public function test_leaving_the_secret_fields_blank_keeps_the_previously_saved_value(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        FirebaseSettings::save([
            'vapid_key' => 'original-vapid-key',
            'service_account_json' => json_encode([
                'client_email' => 'original@zamzam-erp-app.iam.gserviceaccount.com',
                'private_key' => 'original-key',
            ]),
        ]);

        Livewire::actingAs($superAdmin)
            ->test(PushNotificationSettings::class)
            ->set('settings.api_key', 'updated-api-key-only')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('updated-api-key-only', FirebaseSettings::webConfig()['apiKey']);
        $this->assertSame('original-vapid-key', FirebaseSettings::vapidKey());
        $this->assertSame(
            'original@zamzam-erp-app.iam.gserviceaccount.com',
            FirebaseSettings::serviceAccountJson()['client_email'],
        );
    }
}
