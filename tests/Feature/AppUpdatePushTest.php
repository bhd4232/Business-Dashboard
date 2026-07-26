<?php

namespace Tests\Feature;

use App\Models\AppUpdatePushDelivery;
use App\Models\PushDevice;
use App\Models\User;
use App\Services\AppUpdateService;
use App\Services\FirebaseHttpV1Sender;
use App\Support\AppDeployment;
use App\Support\AppRelease;
use App\Support\FirebasePushResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery\MockInterface;
use Tests\TestCase;

class AppUpdatePushTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set([
            'release.deployment_manifest' => base_path('tests/fixtures/missing-deployment.json'),
            'release.asset_manifest' => base_path('tests/fixtures/missing-vite-manifest.json'),
            'native_push.firebase.enabled' => true,
            'native_push.firebase.max_delivery_attempts' => 3,
        ]);
        $this->useDeployment('deployment-1', '2026-07-23T01:00:00.000Z');
    }

    public function test_a_new_deployment_sends_exactly_one_push_per_active_device(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $device = $this->device($user, 'device-token-1');
        $this->useDeployment('deployment-2', '2026-07-23T02:00:00.000Z');

        $this->mock(FirebaseHttpV1Sender::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->twice()->andReturnTrue();
            $mock->shouldReceive('send')
                ->once()
                ->withArgs(function (
                    string $token,
                    string $title,
                    string $body,
                    array $data,
                ): bool {
                    return $token === 'device-token-1'
                        && str_contains($title, 'App update v')
                        && str_contains($body, 'Upgrade App')
                        && $data['kind'] === 'app-update'
                        && $data['deployment_id'] === AppDeployment::id()
                        && $data['action_url'] === '/admin/settings/release-notes';
                })
                ->andReturn(FirebasePushResult::sent('message-1'));
        });

        $this->artisan('release:notify-deploy')->assertSuccessful();
        $this->artisan('release:notify-deploy')->assertSuccessful();

        $delivery = AppUpdatePushDelivery::query()->sole();

        $this->assertSame($device->getKey(), $delivery->push_device_id);
        $this->assertSame(AppUpdatePushDelivery::STATUS_SENT, $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNotNull($delivery->sent_at);
    }

    public function test_stale_firebase_tokens_are_disabled_and_never_retried(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $device = $this->device($user, 'stale-token');
        $this->useDeployment('deployment-3', '2026-07-23T03:00:00.000Z');

        $this->mock(FirebaseHttpV1Sender::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->twice()->andReturnTrue();
            $mock->shouldReceive('send')
                ->once()
                ->andReturn(FirebasePushResult::stale('UNREGISTERED', 'Token is no longer valid.'));
        });

        $this->artisan('release:notify-deploy')->assertSuccessful();
        $this->artisan('release:notify-deploy')->assertSuccessful();

        $this->assertFalse($device->fresh()->is_active);
        $this->assertSame('UNREGISTERED', $device->fresh()->last_error_code);
        $this->assertSame(
            AppUpdatePushDelivery::STATUS_STALE,
            AppUpdatePushDelivery::query()->sole()->status,
        );
    }

    public function test_transient_failures_retry_on_the_next_sync_without_duplicate_rows(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->device($user, 'retry-token');
        $this->useDeployment('deployment-4', '2026-07-23T04:00:00.000Z');

        $this->mock(FirebaseHttpV1Sender::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->twice()->andReturnTrue();
            $mock->shouldReceive('send')
                ->twice()
                ->andReturn(
                    FirebasePushResult::failed('UNAVAILABLE', 'Try again later.'),
                    FirebasePushResult::sent('message-2'),
                );
        });

        $this->artisan('release:notify-deploy')->assertSuccessful();
        $this->artisan('release:notify-deploy')->assertSuccessful();

        $delivery = AppUpdatePushDelivery::query()->sole();

        $this->assertSame(AppUpdatePushDelivery::STATUS_SENT, $delivery->status);
        $this->assertSame(2, $delivery->attempts);
    }

    public function test_a_result_for_a_rotated_token_does_not_close_the_new_tokens_delivery(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $device = $this->device($user, 'old-in-flight-token');
        $this->useDeployment('deployment-rotated-in-flight', '2026-07-23T04:30:00.000Z');

        $this->mock(FirebaseHttpV1Sender::class, function (MockInterface $mock) use ($device): void {
            $mock->shouldReceive('isConfigured')->once()->andReturnTrue();
            $mock->shouldReceive('send')
                ->once()
                ->andReturnUsing(function () use ($device): FirebasePushResult {
                    $device->forceFill([
                        'token' => 'new-in-flight-token',
                        'token_hash' => hash('sha256', 'new-in-flight-token'),
                    ])->save();

                    return FirebasePushResult::sent('message-for-old-token');
                });
        });

        $this->artisan('release:notify-deploy')->assertSuccessful();

        $delivery = AppUpdatePushDelivery::query()->sole();

        $this->assertSame(AppUpdatePushDelivery::STATUS_PENDING, $delivery->status);
        $this->assertNull($delivery->sent_at);
        $this->assertSame('new-in-flight-token', $device->fresh()->token);
    }

    public function test_inactive_users_and_the_currently_acknowledged_deployment_are_not_pushed(): void
    {
        $active = User::factory()->create(['is_active' => true]);
        $inactive = User::factory()->create(['is_active' => false]);
        $this->device($active, 'active-current-token');
        $this->device($inactive, 'inactive-token');

        $this->mock(FirebaseHttpV1Sender::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->once()->andReturnTrue();
            $mock->shouldNotReceive('send');
        });

        $this->artisan('release:notify-deploy')->assertSuccessful();

        $this->assertSame(0, AppUpdatePushDelivery::query()->count());
    }

    public function test_push_is_a_safe_no_op_when_server_credentials_are_disabled(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->device($user, 'disabled-config-token');
        $this->useDeployment('deployment-5', '2026-07-23T05:00:00.000Z');

        $this->mock(FirebaseHttpV1Sender::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->once()->andReturnFalse();
            $mock->shouldNotReceive('send');
        });

        $this->artisan('release:notify-deploy')->assertSuccessful();

        $this->assertSame(0, AppUpdatePushDelivery::query()->count());
    }

    public function test_acknowledgement_records_the_installed_release_identity(): void
    {
        $user = User::factory()->create();
        $commit = str_repeat('a', 40);
        $this->useDeployment('deployment-6', '2026-07-23T06:00:00.000Z', $commit);

        app(AppUpdateService::class)->acknowledge($user);

        $user->refresh();

        $this->assertSame(AppDeployment::id(), $user->acknowledged_app_deployment_id);
        $this->assertSame(
            AppRelease::latestPublished()['version'],
            $user->acknowledged_app_version,
        );
        $this->assertSame($commit, $user->acknowledged_app_commit);
        $this->assertSame(
            '2026-07-23T06:00:00+00:00',
            $user->acknowledged_app_built_at?->toIso8601String(),
        );
    }

    public function test_new_users_start_with_the_current_release_identity_acknowledged(): void
    {
        $commit = str_repeat('b', 40);
        $this->useDeployment('deployment-7', '2026-07-23T07:00:00.000Z', $commit);

        $user = User::factory()->create();

        $this->assertSame(AppDeployment::id(), $user->acknowledged_app_deployment_id);
        $this->assertSame(
            AppRelease::latestPublished()['version'],
            $user->acknowledged_app_version,
        );
        $this->assertSame($commit, $user->acknowledged_app_commit);
        $this->assertSame(
            '2026-07-23T07:00:00+00:00',
            $user->acknowledged_app_built_at?->toIso8601String(),
        );
    }

    protected function device(User $user, string $token): PushDevice
    {
        return PushDevice::query()->create([
            'user_id' => $user->getKey(),
            'token' => $token,
            'token_hash' => hash('sha256', $token),
            'device_id' => 'installation-'.hash('sha256', $token),
            'platform' => 'android',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);
    }

    protected function useDeployment(string $id, string $builtAt, ?string $commit = null): void
    {
        Config::set([
            'release.commit' => $commit,
            'release.deployment_id' => $id,
            'release.deployment_built_at' => $builtAt,
        ]);
    }
}
