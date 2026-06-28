<?php

namespace App\Filament\Pages;

use App\Support\AppRelease;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ReleaseNotes extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 5;

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
