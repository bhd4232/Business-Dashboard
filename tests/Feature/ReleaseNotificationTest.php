<?php

namespace Tests\Feature;

use App\Console\Commands\NotifyLatestRelease;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ReleaseNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_run_records_a_baseline_without_notifying_anyone(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->artisan('release:notify-deploy')->assertSuccessful();

        $this->assertNotNull(AppSetting::getValue(NotifyLatestRelease::LAST_NOTIFIED_VERSION_KEY));
        $this->assertSame(0, $user->fresh()->notifications()->count());
    }

    public function test_a_new_changelog_version_notifies_active_users_and_updates_the_baseline(): void
    {
        AppSetting::setValue(NotifyLatestRelease::LAST_NOTIFIED_VERSION_KEY, '0.0.1');

        $activeUser = User::factory()->create(['is_active' => true]);
        $inactiveUser = User::factory()->create(['is_active' => false]);

        $this->artisan('release:notify-deploy')->assertSuccessful();

        $this->assertSame(1, $activeUser->fresh()->notifications()->count());
        $this->assertSame(0, $inactiveUser->fresh()->notifications()->count());
        $this->assertNotSame('0.0.1', AppSetting::getValue(NotifyLatestRelease::LAST_NOTIFIED_VERSION_KEY));
    }

    public function test_running_again_for_the_same_version_does_not_duplicate_notifications(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        AppSetting::setValue(NotifyLatestRelease::LAST_NOTIFIED_VERSION_KEY, '0.0.1');
        $this->artisan('release:notify-deploy')->assertSuccessful();
        $this->assertSame(1, $user->fresh()->notifications()->count());

        $this->artisan('release:notify-deploy')->assertSuccessful();
        $this->assertSame(1, $user->fresh()->notifications()->count());
    }

    public function test_missing_changelog_falls_back_to_configured_release_version_without_erroring(): void
    {
        Config::set('release.version', '0.0.1');
        $user = User::factory()->create(['is_active' => true]);
        AppSetting::setValue(NotifyLatestRelease::LAST_NOTIFIED_VERSION_KEY, '0.0.1');

        File::partialMock()->shouldReceive('exists')->andReturn(false);
        File::partialMock()->shouldReceive('get')->andReturn('');

        $this->artisan('release:notify-deploy')->assertSuccessful();

        $this->assertSame(0, $user->fresh()->notifications()->count());
    }
}
