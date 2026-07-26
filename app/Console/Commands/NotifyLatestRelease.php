<?php

namespace App\Console\Commands;

use App\Services\AppUpdatePushService;
use App\Services\AppUpdateService;
use Illuminate\Console\Command;

class NotifyLatestRelease extends Command
{
    protected $signature = 'release:notify-deploy';

    protected $description = 'Send in-app and native push notifications for the current application deployment';

    public const LAST_NOTIFIED_VERSION_KEY = AppUpdateService::LAST_NOTIFIED_DEPLOYMENT_KEY;

    public function handle(
        AppUpdateService $appUpdates,
        AppUpdatePushService $appUpdatePush,
    ): int {
        $notified = $appUpdates->synchronize();
        $push = $appUpdatePush->sendCurrentDeployment();

        $this->components->info(
            $notified === 1
                ? '1 user was notified about the current app deployment.'
                : "{$notified} users were notified about the current app deployment.",
        );

        if ($push['configured']) {
            $this->components->info(
                "{$push['sent']} device push notifications sent; "
                ."{$push['stale']} stale tokens disabled; "
                ."{$push['failed']} failed.",
            );
        } elseif ($push['enabled']) {
            $this->components->warn(
                'Firebase push is enabled, but its project or server credentials are incomplete.',
            );
        }

        return self::SUCCESS;
    }
}
