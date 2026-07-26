<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Settings;
use App\Services\AppReleaseStateService;
use App\Support\AppRelease;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ReleaseNotes extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $cluster = Settings::class;

    protected static ?int $navigationSort = 6;

    protected static ?string $title = 'Release Notes';

    protected string $view = 'filament.pages.release-notes';

    public static function canAccess(): bool
    {
        return Auth::check();
    }

    public function releaseState(): array
    {
        return app(AppReleaseStateService::class)->forUser(Auth::user());
    }

    public function changelogEntries(): array
    {
        return $this->installedEntries(AppRelease::userFacingChangelogEntries());
    }

    public function technicalChangelogEntries(): array
    {
        if (! (Auth::user()?->isSuperAdmin() ?? false)) {
            return [];
        }

        return $this->installedEntries(AppRelease::technicalChangelogEntries());
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, array<string, mixed>>
     */
    protected function installedEntries(array $entries): array
    {
        $installedVersion = (string) ($this->releaseState()['installed']['version'] ?? '');

        if ($installedVersion === '' || $installedVersion === 'previous') {
            return array_slice($entries, 1);
        }

        $installedIndex = collect($entries)
            ->search(fn (array $entry): bool => hash_equals(
                (string) $entry['version'],
                $installedVersion,
            ));

        return $installedIndex === false
            ? $entries
            : array_slice($entries, (int) $installedIndex);
    }
}
