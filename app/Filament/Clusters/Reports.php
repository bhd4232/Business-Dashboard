<?php

namespace App\Filament\Clusters;

use App\Filament\Pages\Reports as ReportsPage;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

class Reports extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Reports';

    protected static ?int $navigationSort = 10;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function canAccess(): bool
    {
        return static::canAccessClusteredComponents();
    }

    public function mount(): void
    {
        $target = ReportsPage::getUrl();

        if (filled($query = request()->getQueryString())) {
            $target .= "?{$query}";
        }

        redirect($target);
    }
}
