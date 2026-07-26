<?php

namespace Tests\Feature;

use App\Models\AppUpdatePushDelivery;
use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushDeviceRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_user_can_register_an_encrypted_push_token(): void
    {
        $user = User::factory()->create();
        $token = 'fcm-registration-token-'.str_repeat('a', 80);

        $this->actingAs($user)
            ->postJson(route('admin.push-devices.store'), [
                'token' => $token,
                'device_id' => 'installation-1',
                'platform' => 'android',
                'app_version' => '1.21.0',
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'registered');

        $device = PushDevice::query()->sole();

        $this->assertSame($user->getKey(), $device->user_id);
        $this->assertSame($token, $device->token);
        $this->assertSame(hash('sha256', $token), $device->token_hash);
        $this->assertSame('installation-1', $device->device_id);
        $this->assertTrue($device->is_active);
        $this->assertNotSame(
            $token,
            PushDevice::query()->getQuery()->where('id', $device->getKey())->value('token'),
        );
    }

    public function test_registration_is_idempotent_and_a_rotated_token_reuses_the_installation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('admin.push-devices.store'), [
            'token' => 'first-token',
            'device_id' => 'installation-2',
            'platform' => 'android',
        ])->assertCreated();

        $this->actingAs($user)->postJson(route('admin.push-devices.store'), [
            'token' => 'second-token',
            'device_id' => 'installation-2',
            'platform' => 'android',
            'app_version' => '1.22.0',
        ])->assertOk();

        $device = PushDevice::query()->sole();

        $this->assertSame('second-token', $device->token);
        $this->assertSame(hash('sha256', 'second-token'), $device->token_hash);
        $this->assertSame('1.22.0', $device->app_version);
    }

    public function test_rotating_a_token_reopens_unsent_delivery_for_the_same_installation(): void
    {
        $user = User::factory()->create();
        $device = PushDevice::query()->create([
            'user_id' => $user->getKey(),
            'token' => 'stale-token',
            'token_hash' => hash('sha256', 'stale-token'),
            'device_id' => 'installation-with-stale-token',
            'platform' => 'android',
            'is_active' => false,
            'disabled_at' => now(),
            'last_error_code' => 'UNREGISTERED',
            'last_error_at' => now(),
        ]);
        $delivery = AppUpdatePushDelivery::query()->create([
            'push_device_id' => $device->getKey(),
            'deployment_id' => 'deployment-with-stale-token',
            'status' => AppUpdatePushDelivery::STATUS_STALE,
            'attempts' => 2,
            'last_error_code' => 'UNREGISTERED',
            'last_error' => 'The old token is no longer registered.',
            'attempted_at' => now(),
        ]);

        $this->actingAs($user)->postJson(route('admin.push-devices.store'), [
            'token' => 'rotated-token',
            'device_id' => 'installation-with-stale-token',
            'platform' => 'android',
        ])->assertOk();

        $device->refresh();
        $delivery->refresh();

        $this->assertSame('rotated-token', $device->token);
        $this->assertTrue($device->is_active);
        $this->assertSame(AppUpdatePushDelivery::STATUS_PENDING, $delivery->status);
        $this->assertSame(0, $delivery->attempts);
        $this->assertNull($delivery->last_error_code);
        $this->assertNull($delivery->attempted_at);
    }

    public function test_a_shared_fcm_token_is_reassigned_to_the_current_authenticated_user(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first)->postJson(route('admin.push-devices.store'), [
            'token' => 'shared-device-token',
            'device_id' => 'installation-3',
            'platform' => 'android',
        ])->assertCreated();

        $this->actingAs($second)->postJson(route('admin.push-devices.store'), [
            'token' => 'shared-device-token',
            'device_id' => 'installation-3',
            'platform' => 'android',
        ])->assertOk();

        $this->assertSame(1, PushDevice::query()->count());
        $this->assertSame($second->getKey(), PushDevice::query()->sole()->user_id);
    }

    public function test_registration_requires_authentication_and_valid_native_platform_data(): void
    {
        $this->postJson(route('admin.push-devices.store'), [
            'token' => 'token',
            'platform' => 'android',
        ])->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.push-devices.store'), [
                'token' => 'token',
                'platform' => 'web',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('platform');

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.push-devices.store'), [
                'token' => '   ',
                'platform' => 'android',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('token');
    }

    public function test_a_user_can_disable_only_their_own_registered_device(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $firstDevice = PushDevice::query()->create([
            'user_id' => $first->getKey(),
            'token' => 'first-token',
            'token_hash' => hash('sha256', 'first-token'),
            'device_id' => 'first-installation',
            'platform' => 'android',
        ]);
        $secondDevice = PushDevice::query()->create([
            'user_id' => $second->getKey(),
            'token' => 'second-token',
            'token_hash' => hash('sha256', 'second-token'),
            'device_id' => 'second-installation',
            'platform' => 'android',
        ]);

        $this->actingAs($first)
            ->deleteJson(route('admin.push-devices.destroy'), [
                'device_id' => 'first-installation',
            ])
            ->assertNoContent();

        $this->assertFalse($firstDevice->fresh()->is_active);
        $this->assertTrue($secondDevice->fresh()->is_active);
    }
}
