<?php

namespace App\Support;

use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

final class StorefrontCategoryIcons
{
    private const LEGACY_ICONS = [
        'appliances' => 'heroicon-o-bolt',
        'audio' => 'heroicon-o-speaker-wave',
        'computer' => 'heroicon-o-computer-desktop',
        'electronics' => 'heroicon-o-cpu-chip',
        'home' => 'heroicon-o-home-modern',
        'mobile' => 'heroicon-o-device-phone-mobile',
        'networking' => 'heroicon-o-wifi',
        'office' => 'heroicon-o-briefcase',
        'tools' => 'heroicon-o-wrench-screwdriver',
        'packaging' => 'heroicon-o-cube',
    ];

    private static ?array $catalog = null;

    private static ?array $options = null;

    /**
     * @return array<int, array{value: string, label: string, style: string, search: string}>
     */
    public static function catalog(): array
    {
        return self::$catalog ??= collect(Heroicon::cases())
            ->map(function (Heroicon $icon): array {
                $isOutline = str_starts_with($icon->value, 'o-');
                $name = Str::headline($isOutline ? substr($icon->value, 2) : $icon->value);
                $style = $isOutline ? 'Outline' : 'Solid';
                $value = $icon->getIconForSize(IconSize::Large);

                return [
                    'value' => $value,
                    'label' => "{$name} ({$style})",
                    'style' => strtolower($style),
                    'search' => Str::lower("{$name} {$style} {$value}"),
                ];
            })
            ->sortBy([
                ['style', 'asc'],
                ['label', 'asc'],
            ])
            ->values()
            ->all();
    }

    public static function options(): array
    {
        return self::$options ??= collect(self::catalog())
            ->pluck('label', 'value')
            ->all();
    }

    public static function normalize(?string $icon): ?string
    {
        $icon = self::LEGACY_ICONS[(string) $icon] ?? $icon;

        return array_key_exists((string) $icon, self::options()) ? (string) $icon : null;
    }

    public static function label(?string $icon): ?string
    {
        $icon = self::normalize($icon);

        return $icon ? self::options()[$icon] : null;
    }
}
