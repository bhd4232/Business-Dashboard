<?php

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

class Inventory extends NavigationCluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?string $navigationLabel = 'Inventory';

    protected static ?int $navigationSort = 6;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function canAccess(): bool
    {
        return static::canAccessClusteredComponents();
    }
}
