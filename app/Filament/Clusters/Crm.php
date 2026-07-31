<?php

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

class Crm extends NavigationCluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'CRM';

    protected static ?string $clusterBreadcrumb = 'CRM';

    protected static ?int $navigationSort = 2;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function canAccess(): bool
    {
        return static::canAccessClusteredComponents();
    }
}
