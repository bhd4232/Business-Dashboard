<?php

namespace App\Services;

use App\Models\AppUpdatePushDelivery;
use App\Models\PushDevice;
use App\Support\AppDeployment;
use App\Support\AppRelease;
use App\Support\FirebasePushResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AppUpdatePushService
{
    public function __construct(
        protected FirebaseHttpV1Sender $firebase,
        protected AppUpdateService $appUpdates,
    ) {}

    /**
     * @return array{enabled: bool, configured: bool, sent: int, stale: int, failed: int, skipped: int}
     */
    public function sendCurrentDeployment(): array
    {
        $report = [
            'enabled' => (bool) config('native_push.firebase.enabled', false),
            'configured' => $this->firebase->isConfigured(),
            'sent' => 0,
            'stale' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        if (! $report['configured'] || ! $this->schemaIsReady()) {
            return $report;
        }

        $deployment = AppDeployment::current();

        if (
            ! ($deployment['ready'] ?? false)
            || blank($deployment['deployment_id'] ?? null)
            || ! $this->appUpdates->isDeploymentEligible($deployment)
        ) {
            return $report;
        }

        $deploymentId = (string) $deployment['deployment_id'];
        $release = AppRelease::latestPublished();
        $releaseVersion = filled($release['version'] ?? null)
            ? (string) $release['version']
            : null;
        $lock = Cache::lock('app-update-native-push:'.$deploymentId, 180);

        if (! $lock->get()) {
            $report['skipped']++;

            return $report;
        }

        try {
            PushDevice::query()
                ->where('is_active', true)
                ->whereHas('user', function ($query) use ($deploymentId): void {
                    $query
                        ->where('is_active', true)
                        ->where(function ($query) use ($deploymentId): void {
                            $query
                                ->whereNull('acknowledged_app_deployment_id')
                                ->orWhere('acknowledged_app_deployment_id', '!=', $deploymentId);
                        });
                })
                ->orderBy('id')
                ->eachById(function (PushDevice $device) use (
                    $deployment,
                    $deploymentId,
                    $release,
                    $releaseVersion,
                    &$report,
                ): void {
                    $delivery = $this->reserveDelivery(
                        $device,
                        $deploymentId,
                        $releaseVersion,
                    );

                    if (! $delivery) {
                        $report['skipped']++;

                        return;
                    }

                    try {
                        $result = $this->firebase->send(
                            (string) $device->token,
                            $this->title($releaseVersion),
                            $this->body($release),
                            [
                                'kind' => 'app-update',
                                'deployment_id' => $deploymentId,
                                'release_version' => $releaseVersion ?? '',
                                'commit' => (string) ($deployment['commit'] ?? ''),
                                'built_at' => (string) ($deployment['built_at'] ?? ''),
                                'action_url' => '/admin/settings/release-notes',
                            ],
                        );
                    } catch (Throwable $exception) {
                        $result = FirebasePushResult::failed(
                            'FIREBASE_SEND_EXCEPTION',
                            $exception->getMessage(),
                        );
                    }

                    $this->recordResult($device, $delivery, $result);
                    $report[$result->status]++;
                });
        } finally {
            $lock->release();
        }

        return $report;
    }

    protected function reserveDelivery(
        PushDevice $device,
        string $deploymentId,
        ?string $releaseVersion,
    ): ?AppUpdatePushDelivery {
        return DB::transaction(function () use (
            $deploymentId,
            $device,
            $releaseVersion,
        ): ?AppUpdatePushDelivery {
            $delivery = AppUpdatePushDelivery::query()
                ->where('push_device_id', $device->getKey())
                ->where('deployment_id', $deploymentId)
                ->lockForUpdate()
                ->first();

            if (! $delivery) {
                $delivery = AppUpdatePushDelivery::query()->create([
                    'push_device_id' => $device->getKey(),
                    'deployment_id' => $deploymentId,
                    'release_version' => $releaseVersion,
                    'status' => AppUpdatePushDelivery::STATUS_PENDING,
                ]);
            }

            if (in_array(
                $delivery->status,
                [
                    AppUpdatePushDelivery::STATUS_SENT,
                    AppUpdatePushDelivery::STATUS_STALE,
                ],
                true,
            )) {
                return null;
            }

            if (
                $delivery->status === AppUpdatePushDelivery::STATUS_PROCESSING
                && $delivery->attempted_at?->isAfter(now()->subMinutes(5))
            ) {
                return null;
            }

            $maxAttempts = max(
                1,
                (int) config('native_push.firebase.max_delivery_attempts', 12),
            );

            if ($delivery->attempts >= $maxAttempts) {
                return null;
            }

            $delivery->forceFill([
                'status' => AppUpdatePushDelivery::STATUS_PROCESSING,
                'attempts' => $delivery->attempts + 1,
                'last_error_code' => null,
                'last_error' => null,
                'attempted_at' => now(),
            ])->save();

            return $delivery;
        }, attempts: 3);
    }

    protected function recordResult(
        PushDevice $device,
        AppUpdatePushDelivery $delivery,
        FirebasePushResult $result,
    ): void {
        $tokenIsCurrent = PushDevice::query()
            ->whereKey($device->getKey())
            ->where('token_hash', $device->token_hash)
            ->exists();

        if (! $tokenIsCurrent) {
            $delivery->forceFill([
                'status' => AppUpdatePushDelivery::STATUS_PENDING,
                'last_error_code' => null,
                'last_error' => null,
                'attempted_at' => null,
                'sent_at' => null,
            ])->save();

            return;
        }

        if ($result->wasSent()) {
            $delivery->forceFill([
                'status' => AppUpdatePushDelivery::STATUS_SENT,
                'sent_at' => now(),
                'last_error_code' => null,
                'last_error' => null,
            ])->save();

            $device->forceFill([
                'last_error_code' => null,
                'last_error_at' => null,
            ])->save();

            return;
        }

        if ($result->isStale()) {
            DB::transaction(function () use ($delivery, $device, $result): void {
                $delivery->forceFill([
                    'status' => AppUpdatePushDelivery::STATUS_STALE,
                    'last_error_code' => $result->errorCode,
                    'last_error' => $result->error,
                ])->save();

                PushDevice::query()
                    ->whereKey($device->getKey())
                    ->where('token_hash', $device->token_hash)
                    ->update([
                        'is_active' => false,
                        'disabled_at' => now(),
                        'last_error_code' => $result->errorCode,
                        'last_error_at' => now(),
                        'updated_at' => now(),
                    ]);
            });

            return;
        }

        $delivery->forceFill([
            'status' => AppUpdatePushDelivery::STATUS_FAILED,
            'last_error_code' => $result->errorCode,
            'last_error' => $result->error,
        ])->save();

        PushDevice::query()
            ->whereKey($device->getKey())
            ->where('token_hash', $device->token_hash)
            ->update([
                'last_error_code' => $result->errorCode,
                'last_error_at' => now(),
                'updated_at' => now(),
            ]);

        Log::warning('Native app-update push delivery failed.', [
            'push_device_id' => $device->getKey(),
            'deployment_id' => $delivery->deployment_id,
            'error_code' => $result->errorCode,
        ]);
    }

    /**
     * @param  array<string, mixed>  $release
     */
    protected function body(array $release): string
    {
        $type = filled($release['type_label'] ?? null)
            ? (string) $release['type_label']
            : 'App update';

        return "{$type} is ready. Open the app and tap Upgrade App when you are ready.";
    }

    protected function title(?string $releaseVersion): string
    {
        return $releaseVersion
            ? "App update v{$releaseVersion} available"
            : 'App update available';
    }

    protected function schemaIsReady(): bool
    {
        return Schema::hasTable('push_devices')
            && Schema::hasTable('app_update_push_deliveries')
            && Schema::hasColumn('users', 'acknowledged_app_deployment_id');
    }
}
