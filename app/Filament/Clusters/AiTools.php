<?php

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

/**
 * "AI Tools" — the top-level home for AI-powered utility tools (Image
 * Generation first, Video Generation and Content Creation reserved), kept
 * separate from the per-module AI features that already live inside their
 * own areas (Ad Assistant under Ads, Landing Page Builder under Storefront,
 * Auto Messaging under CRM). Sorted next to Ads (both 7) since it is the
 * other AI/marketing capability area; `Ads` sorts before `AiTools`.
 */
class AiTools extends NavigationCluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $navigationLabel = 'AI Tools';

    protected static ?int $navigationSort = 7;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function canAccess(): bool
    {
        return static::canAccessClusteredComponents();
    }
}
