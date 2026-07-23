<?php

namespace App\Console\Commands;

use App\Services\AppUpdateService;
use Illuminate\Console\Command;

class NotifyLatestRelease extends Command
{
    protected $signature = 'release:notify-deploy';

    protected $description = 'Send an in-app notification when a new application deployment is available';

    public const LAST_NOTIFIED_VERSION_KEY = AppUpdateService::LAST_NOTIFIED_DEPLOYMENT_KEY;

    public function handle(AppUpdateService $appUpdates): int
    {
        $notified = $appUpdates->synchronize();

        $this->components->info(
            $notified === 1
                ? '1 user was notified about the current app deployment.'
                : "{$notified} users were notified about the current app deployment.",
        );

        return self::SUCCESS;
    }
}
