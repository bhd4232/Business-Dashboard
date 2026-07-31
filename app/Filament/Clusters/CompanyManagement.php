<?php

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

class CompanyManagement extends NavigationCluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Company Management';

    protected static ?int $navigationSort = 0;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function canAccess(): bool
    {
        return static::canAccessClusteredComponents();
    }
}
