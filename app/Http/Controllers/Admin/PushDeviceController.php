<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppUpdatePushDelivery;
use App\Models\PushDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PushDeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user, 401);

        $this->normalizeIdentifiers($request);

        $data = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'device_id' => ['nullable', 'string', 'max:191'],
            'platform' => ['required', Rule::in(['android', 'ios'])],
            'app_version' => ['nullable', 'string', 'max:64'],
        ]);
        $token = trim($data['token']);
        $tokenHash = hash('sha256', $token);
        $deviceId = filled($data['device_id'] ?? null)
            ? trim((string) $data['device_id'])
            : null;

        $result = DB::transaction(function () use (
            $data,
            $deviceId,
            $token,
            $tokenHash,
            $user,
        ): array {
            $tokenDevice = PushDevice::query()
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();
            $installationDevice = $deviceId
                ? PushDevice::query()
                    ->where('device_id', $deviceId)
                    ->lockForUpdate()
                    ->first()
                : null;

            if (
                $tokenDevice
                && $installationDevice
                && ! $tokenDevice->is($installationDevice)
            ) {
                $installationDevice->forceFill([
                    'device_id' => null,
                    'is_active' => false,
                    'disabled_at' => now(),
                    'last_error_code' => 'TOKEN_REASSIGNED',
                    'last_error_at' => now(),
                ])->save();
            }

            $device = $tokenDevice ?? $installationDevice ?? new PushDevice;
            $wasRecentlyCreated = ! $device->exists;
            $tokenChanged = $device->exists
                && ! hash_equals((string) $device->token_hash, $tokenHash);

            $device->forceFill([
                'user_id' => $user->getKey(),
                'token' => $token,
                'token_hash' => $tokenHash,
                'device_id' => $deviceId,
                'platform' => $data['platform'],
                'app_version' => $data['app_version'] ?? null,
                'is_active' => true,
                'last_seen_at' => now(),
                'disabled_at' => null,
                'last_error_code' => null,
                'last_error_at' => null,
            ])->save();

            if ($tokenChanged) {
                $device->appUpdateDeliveries()
                    ->whereIn('status', [
                        AppUpdatePushDelivery::STATUS_PENDING,
                        AppUpdatePushDelivery::STATUS_PROCESSING,
                        AppUpdatePushDelivery::STATUS_FAILED,
                        AppUpdatePushDelivery::STATUS_STALE,
                    ])
                    ->update([
                        'status' => AppUpdatePushDelivery::STATUS_PENDING,
                        'attempts' => 0,
                        'last_error_code' => null,
                        'last_error' => null,
                        'attempted_at' => null,
                        'sent_at' => null,
                        'updated_at' => now(),
                    ]);
            }

            return [$device, $wasRecentlyCreated];
        }, attempts: 3);

        /** @var PushDevice $device */
        [$device, $wasRecentlyCreated] = $result;

        return response()->json([
            'status' => 'registered',
            'device_id' => $device->getKey(),
        ], $wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user, 401);

        $this->normalizeIdentifiers($request);

        $data = $request->validate([
            'token' => ['nullable', 'required_without:device_id', 'string', 'max:4096'],
            'device_id' => ['nullable', 'required_without:token', 'string', 'max:191'],
        ]);
        $tokenHash = filled($data['token'] ?? null)
            ? hash('sha256', trim((string) $data['token']))
            : null;
        $deviceId = filled($data['device_id'] ?? null)
            ? trim((string) $data['device_id'])
            : null;

        PushDevice::query()
            ->where('user_id', $user->getKey())
            ->where(function ($query) use ($deviceId, $tokenHash): void {
                $query
                    ->when($tokenHash, fn ($query) => $query->where('token_hash', $tokenHash))
                    ->when(
                        $deviceId,
                        fn ($query) => $tokenHash
                            ? $query->orWhere('device_id', $deviceId)
                            : $query->where('device_id', $deviceId),
                    );
            })
            ->update([
                'is_active' => false,
                'disabled_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->noContent();
    }

    protected function normalizeIdentifiers(Request $request): void
    {
        $request->merge([
            'token' => is_string($request->input('token'))
                ? trim($request->input('token'))
                : $request->input('token'),
            'device_id' => is_string($request->input('device_id'))
                ? trim($request->input('device_id'))
                : $request->input('device_id'),
        ]);
    }
}
