<?php

namespace App\Notifications;

use App\Filament\Pages\ReleaseNotes;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class AppUpdateAvailable extends Notification
{
    /**
     * @param  array<string, mixed>  $deployment
     * @param  array<string, mixed>  $release
     */
    public function __construct(
        protected array $deployment,
        protected array $release,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $version = (string) ($this->release['version'] ?? 'latest');
        $typeLabel = (string) ($this->release['type_label'] ?? 'App update');
        $releaseDate = filled($this->release['date'] ?? null)
            ? ' · '.(string) $this->release['date']
            : '';
        $buildLabel = filled($this->deployment['short_commit'] ?? null)
            ? ' · build '.(string) $this->deployment['short_commit']
            : '';

        $message = FilamentNotification::make()
            ->title("App update v{$version} available")
            ->body("{$typeLabel}{$releaseDate}{$buildLabel}. Upgrade when you are ready; your open app will not reload automatically.")
            ->warning()
            ->actions([
                Action::make('releaseNotes')
                    ->label('View Release Notes')
                    ->url(ReleaseNotes::getUrl(panel: 'admin'))
                    ->button()
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();

        return [
            ...$message,
            'kind' => 'app-update',
            'deployment_id' => (string) $this->deployment['deployment_id'],
            'release_version' => $version,
            'commit' => $this->deployment['commit'] ?? null,
            'built_at' => $this->deployment['built_at'] ?? null,
        ];
    }
}
