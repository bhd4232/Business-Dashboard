<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Settings;
use App\Services\CoolifyDeploymentService;
use App\Support\CoolifySettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

/**
 * "Roll back to a previous deployment" -- a super-admin-only emergency
 * button so a bad release can be undone from inside the dashboard itself,
 * without needing to open Coolify separately. Deliberately code-only (see
 * App\Services\CoolifyDeploymentService's doc comment): it queues a redeploy
 * of an older commit on Coolify and never touches the database, per the
 * owner's explicit scoping decision.
 */
class AppVersions extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static ?string $cluster = Settings::class;

    protected static ?string $navigationLabel = 'App Versions';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'App Versions';

    protected string $view = 'filament.pages.app-versions';

    public ?string $loadError = null;

    public static function canAccess(): bool
    {
        return CoolifySettings::schemaIsReady() && (Auth::user()?->isSuperAdmin() ?? false);
    }

    public function isConfigured(): bool
    {
        return app(CoolifyDeploymentService::class)->isConfigured();
    }

    /**
     * @return array<int, array{deployment_uuid: string, commit: string, commit_message: ?string, created_at: ?string}>
     */
    public function candidates(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        try {
            $this->loadError = null;

            return app(CoolifyDeploymentService::class)->rollbackCandidates(5);
        } catch (Throwable $exception) {
            $this->loadError = $exception->getMessage();

            return [];
        }
    }

    public function rollbackAction(): Action
    {
        return Action::make('rollback')
            ->label('Rollback to this version')
            ->color('danger')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => 'Rollback to '.Str::substr((string) ($arguments['commit'] ?? ''), 0, 7))
            ->modalDescription('This queues a real redeploy on Coolify using this exact commit — the running app restarts on the old code. It does not touch the database or undo any migration.')
            ->modalSubmitActionLabel('Yes, roll back')
            ->action(function (array $arguments): void {
                $commit = (string) ($arguments['commit'] ?? '');

                if ($commit === '') {
                    return;
                }

                try {
                    $result = app(CoolifyDeploymentService::class)->rollback($commit);
                } catch (Throwable $exception) {
                    Notification::make()
                        ->title('Rollback failed')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Rollback queued')
                    ->body($result['message'].' Coolify is rebuilding and redeploying that commit now.')
                    ->success()
                    ->send();
            });
    }
}
