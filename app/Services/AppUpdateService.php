<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\AppUpdateDelivery;
use App\Models\User;
use App\Notifications\AppUpdateAvailable;
use App\Support\AppDeployment;
use App\Support\AppRelease;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AppUpdateService
{
    /**
     * This legacy key is intentionally retained so existing deployments move
     * from their CHANGELOG-version baseline to the new deployment identity.
     */
    public const LAST_NOTIFIED_DEPLOYMENT_KEY = 'release.last_notified_version';

    public const LAST_NOTIFIED_DEPLOYMENT_BUILT_AT_KEY = 'release.last_notified_deployment_built_at';

    protected ?bool $isSchemaReady = null;

    public function isAvailable(?User $user): bool
    {
        if (! $user || ! $this->schemaIsReady()) {
            return false;
        }

        $deployment = AppDeployment::current();

        if (! $this->isDeploymentEligible($deployment)) {
            return false;
        }

        return ! hash_equals(
            (string) ($user->acknowledged_app_deployment_id ?? ''),
            (string) $deployment['deployment_id'],
        );
    }

    /**
     * @param  array<string, mixed>|null  $deployment
     */
    public function isDeploymentEligible(?array $deployment = null): bool
    {
        if (! $this->schemaIsReady()) {
            return false;
        }

        $deployment ??= AppDeployment::current();

        if (! ($deployment['ready'] ?? false)) {
            return false;
        }

        $knownDeploymentId = (string) AppSetting::getValue(self::LAST_NOTIFIED_DEPLOYMENT_KEY, '');

        if (
            $knownDeploymentId !== ''
            && ! hash_equals($knownDeploymentId, (string) $deployment['deployment_id'])
            && ! $this->canAdvanceDeployment(
                $deployment,
                $knownDeploymentId,
                AppSetting::getValue(self::LAST_NOTIFIED_DEPLOYMENT_BUILT_AT_KEY),
            )
        ) {
            return false;
        }

        return true;
    }

    public function synchronize(?User $requestUser = null): int
    {
        if (! $this->schemaIsReady()) {
            return 0;
        }

        $deployment = AppDeployment::current();

        if (! ($deployment['ready'] ?? false) || blank($deployment['deployment_id'] ?? null)) {
            return 0;
        }

        $knownDeploymentId = (string) AppSetting::getValue(self::LAST_NOTIFIED_DEPLOYMENT_KEY, '');
        $knownBuiltAt = AppSetting::getValue(self::LAST_NOTIFIED_DEPLOYMENT_BUILT_AT_KEY);
        $deploymentChanged = ! hash_equals($knownDeploymentId, (string) $deployment['deployment_id']);

        if (
            $deploymentChanged
            && ! $this->canAdvanceDeployment($deployment, $knownDeploymentId, $knownBuiltAt)
        ) {
            return 0;
        }

        if ($requestUser) {
            if (! $this->isAvailable($requestUser)) {
                return 0;
            }

            if (AppUpdateDelivery::query()
                ->where('user_id', $requestUser->getKey())
                ->where('deployment_id', $deployment['deployment_id'])
                ->exists()) {
                return 0;
            }
        }

        $lock = Cache::lock('app-update-notification-sync', 30);

        if (! $lock->get()) {
            return 0;
        }

        try {
            return $this->synchronizeWhileLocked($deployment, $requestUser);
        } finally {
            $lock->release();
        }
    }

    public function acknowledge(User $user): void
    {
        if (! $this->schemaIsReady()) {
            return;
        }

        $deployment = AppDeployment::current();
        $deploymentId = (string) ($deployment['deployment_id'] ?? '');

        if ($deploymentId === '') {
            return;
        }

        DB::transaction(function () use ($user, $deploymentId): void {
            $lockedUser = User::query()
                ->lockForUpdate()
                ->findOrFail($user->getKey());

            $lockedUser->forceFill([
                'acknowledged_app_deployment_id' => $deploymentId,
                'app_upgrade_acknowledged_at' => now(),
            ])->saveQuietly();

            $lockedUser->unreadNotifications()
                ->where('data->kind', 'app-update')
                ->update(['read_at' => now()]);
        });
    }

    /**
     * @param  array<string, mixed>  $deployment
     */
    protected function synchronizeWhileLocked(array $deployment, ?User $requestUser): int
    {
        return DB::transaction(function () use ($deployment, $requestUser): int {
            $deploymentId = (string) $deployment['deployment_id'];

            AppSetting::query()->firstOrCreate(
                ['key' => self::LAST_NOTIFIED_DEPLOYMENT_KEY],
                ['value' => null, 'is_encrypted' => false],
            );
            AppSetting::query()->firstOrCreate(
                ['key' => self::LAST_NOTIFIED_DEPLOYMENT_BUILT_AT_KEY],
                ['value' => null, 'is_encrypted' => false],
            );

            $states = AppSetting::query()
                ->whereIn('key', [
                    self::LAST_NOTIFIED_DEPLOYMENT_KEY,
                    self::LAST_NOTIFIED_DEPLOYMENT_BUILT_AT_KEY,
                ])
                ->lockForUpdate()
                ->get()
                ->keyBy('key');
            $state = $states->get(self::LAST_NOTIFIED_DEPLOYMENT_KEY);
            $builtAtState = $states->get(self::LAST_NOTIFIED_DEPLOYMENT_BUILT_AT_KEY);

            if (! $state || ! $builtAtState) {
                return 0;
            }

            $deploymentChanged = ! hash_equals((string) ($state->value ?? ''), $deploymentId);

            if (
                $deploymentChanged
                && ! $this->canAdvanceDeployment(
                    $deployment,
                    (string) ($state->value ?? ''),
                    $builtAtState->value,
                )
            ) {
                return 0;
            }

            $recipients = $requestUser
                ? collect([$requestUser->fresh()])
                    ->filter(fn (?User $user): bool => (bool) $user
                        && $user->is_active !== false
                        && $this->isAvailable($user)
                        && ! AppUpdateDelivery::query()
                            ->where('user_id', $user->getKey())
                            ->where('deployment_id', $deploymentId)
                            ->exists())
                : User::query()
                    ->where('is_active', true)
                    ->where(function ($query) use ($deploymentId): void {
                        $query
                            ->whereNull('acknowledged_app_deployment_id')
                            ->orWhere('acknowledged_app_deployment_id', '!=', $deploymentId);
                    })
                    ->whereNotExists(function ($query) use ($deploymentId): void {
                        $query
                            ->selectRaw('1')
                            ->from('app_update_deliveries')
                            ->whereColumn('app_update_deliveries.user_id', 'users.id')
                            ->where('app_update_deliveries.deployment_id', $deploymentId);
                    })
                    ->get();

            $notified = 0;
            $release = AppRelease::latestPublished();

            foreach ($recipients as $recipient) {
                $recipient = User::query()
                    ->lockForUpdate()
                    ->find($recipient->getKey());

                if (
                    ! $recipient
                    || $recipient->is_active === false
                    || hash_equals(
                        (string) ($recipient->acknowledged_app_deployment_id ?? ''),
                        $deploymentId,
                    )
                ) {
                    continue;
                }

                $delivery = AppUpdateDelivery::query()->firstOrCreate(
                    [
                        'user_id' => $recipient->getKey(),
                        'deployment_id' => $deploymentId,
                    ],
                    [
                        'release_version' => $release['version'] ?? null,
                    ],
                );

                if (! $delivery->wasRecentlyCreated) {
                    continue;
                }

                $recipient->notifyNow(new AppUpdateAvailable($deployment, $release));
                $delivery->forceFill(['notified_at' => now()])->save();
                $notified++;
            }

            if ($deploymentChanged) {
                $state->forceFill([
                    'value' => $deploymentId,
                    'is_encrypted' => false,
                ])->save();
                $builtAtState->forceFill([
                    'value' => $deployment['built_at'] ?? null,
                    'is_encrypted' => false,
                ])->save();
            }

            return $notified;
        }, attempts: 3);
    }

    protected function schemaIsReady(): bool
    {
        return $this->isSchemaReady ??= Schema::hasTable('app_update_deliveries')
            && Schema::hasTable('notifications')
            && Schema::hasTable('app_settings')
            && Schema::hasColumn('users', 'acknowledged_app_deployment_id');
    }

    /**
     * @param  array<string, mixed>  $deployment
     */
    protected function canAdvanceDeployment(
        array $deployment,
        string $knownDeploymentId,
        mixed $knownBuiltAt,
    ): bool {
        $deploymentId = (string) ($deployment['deployment_id'] ?? '');

        if ($knownDeploymentId === '' || hash_equals($knownDeploymentId, $deploymentId)) {
            return true;
        }

        $knownTimestamp = $this->deploymentTimestamp($knownBuiltAt);

        if ($knownTimestamp === null) {
            return true;
        }

        $currentTimestamp = $this->deploymentTimestamp($deployment['built_at'] ?? null);

        return $currentTimestamp !== null && $currentTimestamp > $knownTimestamp;
    }

    protected function deploymentTimestamp(mixed $value): ?float
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        try {
            return (float) (new \DateTimeImmutable((string) $value))->format('U.u');
        } catch (Throwable) {
            return null;
        }
    }
}
