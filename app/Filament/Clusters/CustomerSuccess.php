<?php

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

class CustomerSuccess extends NavigationCluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Customer Success';

    protected static ?int $navigationSort = 8;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function canAccess(): bool
    {
        return static::canAccessClusteredComponents();
    }
}
