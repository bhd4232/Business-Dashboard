<?php

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

class Storefront extends NavigationCluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'Site';

    protected static ?string $clusterBreadcrumb = 'Site';

    protected static ?int $navigationSort = 1;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function canAccess(): bool
    {
        return static::canAccessClusteredComponents();
    }
}
