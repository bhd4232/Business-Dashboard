<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AppUpdateService;
use App\Support\AppDeployment;
use Filament\Auth\Pages\EditProfile;
use Filament\Facades\Filament;
use Filament\Livewire\DatabaseNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AppUpgradeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set([
            'release.deployment_manifest' => base_path('tests/fixtures/missing-deployment.json'),
            'release.asset_manifest' => base_path('tests/fixtures/missing-vite-manifest.json'),
        ]);
        $this->useDeployment('deployment-1', '2026-07-23T01:00:00.000Z');

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_sync_endpoint_creates_the_update_notification_without_a_queue_worker(): void
    {
        $user = User::factory()->create();
        $this->useDeployment('deployment-2', '2026-07-23T02:00:00.000Z');

        $response = $this->actingAs($user)
            ->postJson(route('admin.app-updates.sync'), [
                'deployment_id' => AppDeployment::id(),
            ])
            ->assertOk()
            ->assertHeader('Cache-Control')
            ->assertJson([
                'deployment_id' => AppDeployment::id(),
                'ready' => true,
                'upgrade_available' => true,
            ]);

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame(1, $user->fresh()->notifications()->count());
    }

    public function test_sync_endpoint_rejects_a_deployment_from_another_rolling_node(): void
    {
        $user = User::factory()->create();
        $this->useDeployment('deployment-2', '2026-07-23T02:00:00.000Z');

        $response = $this->actingAs($user)
            ->postJson(route('admin.app-updates.sync'), [
                'deployment_id' => AppDeployment::identity('configured', 'deployment-3'),
            ])
            ->assertConflict()
            ->assertJsonPath('upgrade_available', false);

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame(0, $user->fresh()->notifications()->count());
    }

    public function test_upgrade_acknowledges_the_deployment_marks_its_notification_read_and_forces_uncached_reload(): void
    {
        $user = User::factory()->create();
        $this->useDeployment('deployment-3', '2026-07-23T03:00:00.000Z');

        app(AppUpdateService::class)->synchronize($user);

        $response = $this->actingAs($user)->post(route('admin.app-upgrade'), [
            'return_to' => url('/admin/settings/release-notes'),
            'deployment_id' => AppDeployment::id(),
        ]);

        $response
            ->assertRedirectContains('/admin/settings/release-notes')
            ->assertHeader('Clear-Site-Data', '"cache"')
            ->assertHeader('Cache-Control');

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('_app_upgrade=', $response->headers->get('Location'));
        $this->assertSame(AppDeployment::id(), $user->fresh()->acknowledged_app_deployment_id);
        $this->assertNotNull($user->fresh()->app_upgrade_acknowledged_at);
        $this->assertNotNull($user->fresh()->notifications()->sole()->read_at);
        $this->assertFalse(app(AppUpdateService::class)->isAvailable($user->fresh()));
    }

    public function test_upgrade_rejects_an_external_return_url(): void
    {
        $user = User::factory()->create();
        $this->useDeployment('deployment-8', '2026-07-23T08:00:00.000Z');

        $response = $this->actingAs($user)->post(route('admin.app-upgrade'), [
            'return_to' => 'https://evil.example/steal-session',
            'deployment_id' => AppDeployment::id(),
        ]);

        $response->assertRedirectContains('/admin?_app_upgrade=');
        $this->assertStringNotContainsString('evil.example', $response->headers->get('Location'));
    }

    public function test_upgrade_does_not_acknowledge_a_deployment_that_changed_between_confirmation_and_post(): void
    {
        $user = User::factory()->create();
        $acknowledged = $user->acknowledged_app_deployment_id;

        $this->useDeployment('deployment-6', '2026-07-23T06:00:00.000Z');

        $response = $this->actingAs($user)->post(route('admin.app-upgrade'), [
            'return_to' => url('/admin'),
            'deployment_id' => AppDeployment::identity('configured', 'deployment-7'),
        ]);

        $response
            ->assertRedirect(url('/admin'))
            ->assertHeaderMissing('Clear-Site-Data');

        $this->assertSame($acknowledged, $user->fresh()->acknowledged_app_deployment_id);
        $this->assertTrue(app(AppUpdateService::class)->isAvailable($user->fresh()));
    }

    public function test_upgrade_does_not_acknowledge_an_unready_deployment(): void
    {
        $user = User::factory()->create();
        $acknowledged = $user->acknowledged_app_deployment_id;

        Config::set([
            'release.commit' => null,
            'release.deployment_id' => null,
            'release.deployment_built_at' => null,
        ]);
        $deployment = AppDeployment::current();

        $this->assertFalse($deployment['ready']);

        $response = $this->actingAs($user)->post(route('admin.app-upgrade'), [
            'return_to' => url('/admin'),
            'deployment_id' => $deployment['deployment_id'],
        ]);

        $response->assertHeaderMissing('Clear-Site-Data');
        $this->assertSame($acknowledged, $user->fresh()->acknowledged_app_deployment_id);
    }

    public function test_upgrade_is_a_no_op_when_the_user_already_acknowledged_the_current_deployment(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.app-upgrade'), [
            'return_to' => url('/admin'),
            'deployment_id' => AppDeployment::id(),
        ]);

        $response
            ->assertRedirect(url('/admin'))
            ->assertHeaderMissing('Clear-Site-Data');

        $this->assertFalse(app(AppUpdateService::class)->isAvailable($user->fresh()));
        $this->assertSame(0, $user->fresh()->notifications()->count());
    }

    public function test_acknowledging_the_latest_deployment_clears_obsolete_update_alerts_only(): void
    {
        $user = User::factory()->create();

        $this->useDeployment('deployment-9', '2026-07-23T09:00:00.000Z');
        app(AppUpdateService::class)->synchronize($user);

        $this->useDeployment('deployment-10', '2026-07-23T10:00:00.000Z');
        app(AppUpdateService::class)->synchronize($user);

        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'application',
            'data' => [
                'format' => 'filament',
                'title' => 'Unrelated business alert',
                'kind' => 'business-alert',
            ],
        ]);

        $this->assertSame(3, $user->fresh()->unreadNotifications()->count());

        app(AppUpdateService::class)->acknowledge($user->fresh());

        $remaining = $user->fresh()->unreadNotifications()->get();

        $this->assertCount(1, $remaining);
        $this->assertSame('business-alert', $remaining->sole()->data['kind']);
    }

    public function test_profile_settings_route_is_available_and_updates_only_the_signed_in_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Before Name',
            'role' => 'manager',
        ]);

        $this->actingAs($user)
            ->get(route('filament.admin.auth.profile'))
            ->assertOk()
            ->assertSee('Profile')
            ->assertSee('Before Name');

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->fillForm([
                'name' => 'Updated Name',
                'email' => $user->email,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Updated Name', $user->fresh()->name);
        $this->assertSame('manager', $user->fresh()->role);
    }

    public function test_profile_settings_requires_the_current_password_before_changing_password(): void
    {
        $user = User::factory()->create([
            'role' => 'manager',
            'password' => 'password',
        ]);

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->fillForm([
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'NewPassword123!',
                'passwordConfirmation' => 'NewPassword123!',
                'currentPassword' => 'incorrect-password',
            ])
            ->call('save')
            ->assertHasFormErrors(['currentPassword']);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->fillForm([
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'NewPassword123!',
                'passwordConfirmation' => 'NewPassword123!',
                'currentPassword' => 'password',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
        $this->assertSame('manager', $user->fresh()->role);
    }

    public function test_profile_menu_places_a_highlighted_upgrade_action_immediately_before_sign_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $currentItems = Filament::getPanel('admin')->getUserMenuItems();

        $this->assertSame('Profile Settings', $currentItems['profile']->getLabel());
        $this->assertArrayHasKey('hidden', $currentItems['upgradeApp']->getExtraAttributes());

        $this->useDeployment('deployment-4', '2026-07-23T04:00:00.000Z');

        $updatedItems = Filament::getPanel('admin')->getUserMenuItems();
        $upgrade = $updatedItems['upgradeApp'];

        $this->assertSame('Upgrade App', $upgrade->getLabel());
        $this->assertSame('New', $upgrade->getBadge());
        $this->assertSame('warning', $upgrade->getColor());
        $this->assertArrayNotHasKey('hidden', $upgrade->getExtraAttributes());
        $this->assertLessThan($updatedItems['logout']->getSort(), $upgrade->getSort());
        $this->assertSame(PHP_INT_MAX - 1, $upgrade->getSort());
    }

    public function test_native_notification_bell_polls_and_can_mark_an_app_update_as_read(): void
    {
        $user = User::factory()->create();
        $this->useDeployment('deployment-5', '2026-07-23T05:00:00.000Z');

        app(AppUpdateService::class)->synchronize($user);
        $notification = $user->fresh()->unreadNotifications()->sole();

        $panel = Filament::getPanel('admin');
        $this->assertTrue($panel->hasDatabaseNotifications());
        $this->assertFalse($panel->hasLazyLoadedDatabaseNotifications());
        $this->assertSame('15s', $panel->getDatabaseNotificationsPollingInterval());

        Livewire::actingAs($user)
            ->test(DatabaseNotifications::class)
            ->assertSee($notification->data['title'])
            ->call('markNotificationAsRead', $notification->getKey());

        $this->assertNotNull($notification->fresh()->read_at);
    }

    protected function useDeployment(string $id, string $builtAt): void
    {
        Config::set([
            'release.commit' => null,
            'release.deployment_id' => $id,
            'release.deployment_built_at' => $builtAt,
        ]);
    }
}
