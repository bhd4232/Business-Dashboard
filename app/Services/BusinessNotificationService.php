<?php

namespace App\Services;

use App\Models\Company;
use App\Models\PushDevice;
use App\Models\User;
use App\Notifications\BusinessAlert;
use App\Support\FirebasePushResult;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Dispatches real-time business-event notifications (new order, order
 * status change, new staff, company events) to the right users -- both as
 * an in-app/bell record (Laravel's standard `notifications` table) and as
 * an OS-level push (Android + desktop browser) via the existing
 * FirebaseHttpV1Sender.
 *
 * Two entry points, matching the owner's explicit rules:
 * - notifyCompany(): strictly scoped to one company's active users, further
 *   gated by a permission (e.g. `notifications.orders`) so a user without
 *   that permission never receives the alert -- this is the only path that
 *   ever resolves recipients from a Company, so cross-company leakage is
 *   structurally impossible here (no query ever spans multiple companies).
 * - notifySuperAdmins(): global, unconditional for every active
 *   `role = 'super_admin'` user (e.g. "a new company was created") -- no
 *   permission check, since only super admins should ever see this class
 *   of event in the first place.
 */
class BusinessNotificationService
{
    public function __construct(
        protected FirebaseHttpV1Sender $firebase,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function notifyCompany(
        Company $company,
        string $alertKind,
        string $permission,
        string $title,
        string $body,
        array $data = [],
        ?string $actionUrl = null,
        ?string $actionLabel = null,
    ): void {
        $recipients = User::query()
            ->whereHas('companies', fn ($query) => $query->whereKey($company->getKey()))
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user): bool => $user->hasPermission($permission))
            ->values();

        $this->dispatch(
            $recipients,
            $alertKind,
            $title,
            $body,
            [...$data, 'company_id' => (string) $company->getKey()],
            $actionUrl,
            $actionLabel,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function notifySuperAdmins(
        string $alertKind,
        string $title,
        string $body,
        array $data = [],
        ?string $actionUrl = null,
        ?string $actionLabel = null,
    ): void {
        $recipients = User::query()
            ->where('role', 'super_admin')
            ->where('is_active', true)
            ->get();

        $this->dispatch($recipients, $alertKind, $title, $body, $data, $actionUrl, $actionLabel);
    }

    /**
     * @param  Collection<int, User>  $recipients
     * @param  array<string, mixed>  $data
     */
    protected function dispatch(
        Collection $recipients,
        string $alertKind,
        string $title,
        string $body,
        array $data,
        ?string $actionUrl,
        ?string $actionLabel,
    ): void {
        $firebaseReady = $this->firebase->isConfigured();

        foreach ($recipients as $user) {
            $user->notify(new BusinessAlert($alertKind, $title, $body, $data, $actionUrl, $actionLabel));

            if (! $firebaseReady) {
                continue;
            }

            foreach ($user->pushDevices()->where('is_active', true)->get() as $device) {
                $this->sendToDevice($device, $alertKind, $title, $body, $data, $actionUrl);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function sendToDevice(
        PushDevice $device,
        string $alertKind,
        string $title,
        string $body,
        array $data,
        ?string $actionUrl,
    ): void {
        $payload = [
            'kind' => 'business-alert',
            'alert_kind' => $alertKind,
            ...$data,
        ];

        if (filled($actionUrl)) {
            $payload['action_url'] = $actionUrl;
        }

        try {
            $result = $this->firebase->send(
                (string) $device->token,
                $title,
                $body,
                $payload,
                channelId: 'business-alerts',
            );
        } catch (Throwable $exception) {
            $result = FirebasePushResult::failed(
                'FIREBASE_SEND_EXCEPTION',
                $exception->getMessage(),
            );
        }

        if ($result->isStale()) {
            // Same semantics as AppUpdatePushService's stale branch -- a
            // dead/unregistered token is deactivated immediately. Business
            // alerts are one-shot events, not retried deployments, so no
            // delivery-tracking table is needed here.
            PushDevice::query()
                ->whereKey($device->getKey())
                ->update([
                    'is_active' => false,
                    'disabled_at' => now(),
                    'last_error_code' => $result->errorCode,
                    'last_error_at' => now(),
                    'updated_at' => now(),
                ]);

            return;
        }

        if (! $result->wasSent()) {
            Log::warning('Business alert push delivery failed.', [
                'push_device_id' => $device->getKey(),
                'alert_kind' => $alertKind,
                'error_code' => $result->errorCode,
            ]);
        }
    }
}
