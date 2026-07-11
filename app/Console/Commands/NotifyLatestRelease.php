<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\User;
use App\Support\AppRelease;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;

class NotifyLatestRelease extends Command
{
    protected $signature = 'release:notify-deploy';

    protected $description = 'Send a database notification to active users when a new CHANGELOG version has been deployed';

    public const LAST_NOTIFIED_VERSION_KEY = 'release.last_notified_version';

    public function handle(): int
    {
        $latest = AppRelease::latestPublished();
        $version = $latest['version'];

        $lastNotified = AppSetting::getValue(self::LAST_NOTIFIED_VERSION_KEY);

        // First run ever (no stored value): just record the current version
        // as the baseline so existing installs aren't spammed with every
        // historical release the first time this command runs.
        if ($lastNotified === null) {
            AppSetting::setValue(self::LAST_NOTIFIED_VERSION_KEY, $version);

            return self::SUCCESS;
        }

        if ($lastNotified === $version) {
            return self::SUCCESS;
        }

        $recipients = User::query()->where('is_active', true)->get();

        if ($recipients->isNotEmpty()) {
            Notification::make()
                ->title("New version v{$version} released")
                ->body($latest['type_label'].($latest['date'] ? " · {$latest['date']}" : '').' — open Release Notes for details.')
                ->success()
                ->sendToDatabase($recipients);
        }

        AppSetting::setValue(self::LAST_NOTIFIED_VERSION_KEY, $version);

        return self::SUCCESS;
    }
}
