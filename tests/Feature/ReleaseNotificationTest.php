<?php

namespace Tests\Feature;

use App\Console\Commands\NotifyLatestRelease;
use App\Models\AppSetting;
use App\Models\AppUpdateDelivery;
use App\Models\User;
use App\Services\AppUpdateService;
use App\Support\AppDeployment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ReleaseNotificationTest extends TestCase
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
    }

    public function test_first_run_baselines_a_fresh_install_without_notifying_current_users(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->artisan('release:notify-deploy')->assertSuccessful();

        $this->assertSame(
            AppDeployment::id(),
            AppSetting::getValue(NotifyLatestRelease::LAST_NOTIFIED_VERSION_KEY),
        );
        $this->assertSame(0, $user->fresh()->notifications()->count());
    }

    public function test_a_new_deployment_notifies_active_users_even_when_the_changelog_version_is_unchanged(): void
    {
        $activeUser = User::factory()->create(['is_active' => true]);
        $inactiveUser = User::factory()->create(['is_active' => false]);

        $this->useDeployment('deployment-2', '2026-07-23T02:00:00.000Z');

        $this->artisan('release:notify-deploy')->assertSuccessful();

        $notification = $activeUser->fresh()->notifications()->sole();
        $data = $notification->data;

        $this->assertSame(0, $inactiveUser->fresh()->notifications()->count());
        $this->assertSame('filament', $data['format']);
        $this->assertSame('app-update', $data['kind']);
        $this->assertSame(AppDeployment::id(), $data['deployment_id']);
        $this->assertSame(AppDeployment::id(), AppSetting::getValue(NotifyLatestRelease::LAST_NOTIFIED_VERSION_KEY));
        $this->assertStringContainsString('App update v', $data['title']);
        $this->assertStringContainsString('/admin/settings/release-notes', $data['actions'][0]['url']);
        $this->assertTrue($data['actions'][0]['shouldMarkAsRead']);
    }

    public function test_running_again_for_the_same_deployment_is_strictly_deduplicated_per_user(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->useDeployment('deployment-3', '2026-07-23T03:00:00.000Z');

        $this->artisan('release:notify-deploy')->assertSuccessful();
        $this->artisan('release:notify-deploy')->assertSuccessful();

        $this->assertSame(1, $user->fresh()->notifications()->count());
        $this->assertSame(1, AppUpdateDelivery::query()->count());
    }

    public function test_a_reactivated_user_receives_the_current_update_on_their_next_admin_request(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $this->useDeployment('deployment-4', '2026-07-23T04:00:00.000Z');

        $this->artisan('release:notify-deploy')->assertSuccessful();
        $this->assertSame(0, $user->fresh()->notifications()->count());

        $user->update(['is_active' => true]);

        app(AppUpdateService::class)->synchronize($user);

        $this->assertSame(1, $user->fresh()->notifications()->count());
        $this->assertSame('app-update', $user->fresh()->notifications()->sole()->data['kind']);
    }

    public function test_users_created_on_the_current_deployment_start_acknowledged(): void
    {
        $user = User::factory()->create();

        $this->assertSame(AppDeployment::id(), $user->acknowledged_app_deployment_id);
        $this->assertNotNull($user->app_upgrade_acknowledged_at);
        $this->assertFalse(app(AppUpdateService::class)->isAvailable($user));
    }

    public function test_an_older_rolling_deployment_cannot_replace_the_newer_baseline_or_renotify(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->useDeployment('deployment-5', '2026-07-23T05:00:00.000Z');
        app(AppUpdateService::class)->synchronize($user);
        $newerDeploymentId = AppDeployment::id();
        app(AppUpdateService::class)->acknowledge($user->fresh());

        $this->useDeployment('deployment-1', '2026-07-23T01:00:00.000Z');
        app(AppUpdateService::class)->synchronize($user);

        $this->assertSame(
            $newerDeploymentId,
            AppSetting::getValue(AppUpdateService::LAST_NOTIFIED_DEPLOYMENT_KEY),
        );
        $this->assertSame(1, $user->fresh()->notifications()->count());
        $this->assertSame(1, AppUpdateDelivery::query()->count());
        $this->assertFalse(app(AppUpdateService::class)->isAvailable($user->fresh()));
        $this->get('/health/version')
            ->assertOk()
            ->assertJsonPath('deployment_ready', true)
            ->assertJsonPath('ready', false);
    }

    public function test_request_sync_notifies_only_that_user_and_the_command_delivers_missing_users(): void
    {
        $first = User::factory()->create(['is_active' => true]);
        $second = User::factory()->create(['is_active' => true]);

        $this->useDeployment('deployment-6', '2026-07-23T06:00:00.000Z');

        app(AppUpdateService::class)->synchronize($first);

        $this->assertSame(1, $first->fresh()->notifications()->count());
        $this->assertSame(0, $second->fresh()->notifications()->count());

        $this->artisan('release:notify-deploy')->assertSuccessful();

        $this->assertSame(1, $first->fresh()->notifications()->count());
        $this->assertSame(1, $second->fresh()->notifications()->count());
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
