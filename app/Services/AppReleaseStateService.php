<?php

namespace App\Services;

use App\Models\AppUpdateDelivery;
use App\Models\User;
use App\Support\AppDeployment;
use App\Support\AppRelease;
use Illuminate\Support\Facades\Schema;

class AppReleaseStateService
{
    public function __construct(
        protected AppUpdateService $appUpdates,
    ) {}

    /**
     * @return array{
     *     update_available: bool,
     *     installed: array<string, mixed>,
     *     available: array<string, mixed>|null,
     *     installed_deployment_id: string|null,
     *     available_deployment_id: string
     * }
     */
    public function forUser(?User $user): array
    {
        $availableRelease = AppRelease::latestPublished();
        $deployment = AppDeployment::current();
        $availableDeploymentId = (string) ($deployment['deployment_id'] ?? '');
        $installedDeploymentId = filled($user?->acknowledged_app_deployment_id)
            ? (string) $user->acknowledged_app_deployment_id
            : null;
        $updateAvailable = $this->appUpdates->isAvailable($user);

        if (! $updateAvailable) {
            return [
                'update_available' => false,
                'installed' => $availableRelease,
                'available' => null,
                'installed_deployment_id' => $installedDeploymentId ?: $availableDeploymentId,
                'available_deployment_id' => $availableDeploymentId,
            ];
        }

        $installedVersion = $this->acknowledgedVersion($user)
            ?? $this->deliveryVersion($user, $installedDeploymentId)
            ?? AppRelease::previousPublishedVersion();
        $installedRelease = AppRelease::published($installedVersion)
            ?? [
                'version' => $installedVersion ?: 'previous',
                'type' => null,
                'type_label' => 'Previously installed release',
                'date' => null,
                'commit' => null,
                'short_commit' => null,
            ];

        return [
            'update_available' => true,
            'installed' => $installedRelease,
            'available' => $availableRelease,
            'installed_deployment_id' => $installedDeploymentId,
            'available_deployment_id' => $availableDeploymentId,
        ];
    }

    protected function acknowledgedVersion(?User $user): ?string
    {
        if (
            ! $user
            || ! Schema::hasColumn('users', 'acknowledged_app_version')
            || blank($user->acknowledged_app_version)
        ) {
            return null;
        }

        return (string) $user->acknowledged_app_version;
    }

    protected function deliveryVersion(?User $user, ?string $deploymentId): ?string
    {
        if (
            ! $user
            || blank($deploymentId)
            || ! Schema::hasTable('app_update_deliveries')
        ) {
            return null;
        }

        $version = AppUpdateDelivery::query()
            ->where('user_id', $user->getKey())
            ->where('deployment_id', $deploymentId)
            ->value('release_version');

        return filled($version) ? (string) $version : null;
    }
}
