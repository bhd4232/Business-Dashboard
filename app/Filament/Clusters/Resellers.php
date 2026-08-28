<?php

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * Deliberately its own top-level nav entry, not nested under Sales/CRM --
 * the owner asked for resellers to be managed fully separately from
 * regular Customers, not just a filtered view of the same area.
 */
class Resellers extends NavigationCluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Resellers';

    protected static ?int $navigationSort = 6;

    public static function canAccess(): bool
    {
        return static::canAccessClusteredComponents();
    }
}
