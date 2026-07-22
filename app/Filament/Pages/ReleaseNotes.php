<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Settings;
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

    public function release(): array
    {
        return AppRelease::latestPublished();
    }

    public function changelogEntries(): array
    {
        return AppRelease::userFacingChangelogEntries();
    }

    public function technicalChangelogEntries(): array
    {
        if (! (Auth::user()?->isSuperAdmin() ?? false)) {
            return [];
        }

        return AppRelease::technicalChangelogEntries();
    }
}
