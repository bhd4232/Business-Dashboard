<?php

namespace App\Support;

final class StorefrontThemeRegistry
{
    public const BUILT_IN = 'builtin';

    public const MARKETPLACE_PRO = 'marketplace_pro';

    public const NOOR_SOLAR = 'noor_solar';

    public const BUILT_IN_DEFAULT = 'default';

    public const MARKETPLACE_HERO = 'hero_driven';

    public const MARKETPLACE_CAMPAIGN = 'campaign_driven';

    public const MARKETPLACE_COMPACT = 'compact_dense';

    public const NOOR_SOLAR_ENGINEERED = 'solar_engineered';

    public static function themes(): array
    {
        return [
            self::BUILT_IN => [
                'label' => 'Built-in Theme',
                'description' => 'The current flexible storefront with slides, category scroller, offers, and configurable product carousels.',
                'templates' => [
                    self::BUILT_IN_DEFAULT => 'Default homepage',
                ],
                'default_template' => self::BUILT_IN_DEFAULT,
                'views' => [
                    self::BUILT_IN_DEFAULT => 'storefront.home',
                ],
            ],
            self::MARKETPLACE_PRO => [
                'label' => 'Marketplace Pro',
                'description' => 'A modern B2B commerce theme from the ERP Frontend Design System with hero, campaign, and dense catalog homepages.',
                'templates' => [
                    self::MARKETPLACE_HERO => 'Hero-driven',
                    self::MARKETPLACE_CAMPAIGN => 'Campaign-driven',
                    self::MARKETPLACE_COMPACT => 'Compact & dense',
                ],
                'default_template' => self::MARKETPLACE_HERO,
                'views' => [
                    self::MARKETPLACE_HERO => 'storefront.themes.marketplace-pro.home',
                    self::MARKETPLACE_CAMPAIGN => 'storefront.themes.marketplace-pro.home',
                    self::MARKETPLACE_COMPACT => 'storefront.themes.marketplace-pro.home',
                ],
            ],
            self::NOOR_SOLAR => [
                'label' => 'Noor Solar Energy',
                'description' => 'A premium solar commerce theme with application-led discovery, ERP stock signals, project quotation paths, and an interactive module lab.',
                'templates' => [
                    self::NOOR_SOLAR_ENGINEERED => 'Solar engineered',
                ],
                'default_template' => self::NOOR_SOLAR_ENGINEERED,
                'views' => [
                    self::NOOR_SOLAR_ENGINEERED => 'storefront.themes.noor-solar.home',
                ],
            ],
        ];
    }

    public static function themeOptions(): array
    {
        return collect(self::themes())
            ->mapWithKeys(fn (array $theme, string $key): array => [$key => $theme['label']])
            ->all();
    }

    public static function templateOptions(?string $theme): array
    {
        return self::themes()[self::normalizeTheme($theme)]['templates'];
    }

    public static function themeDescription(?string $theme): string
    {
        return self::themes()[self::normalizeTheme($theme)]['description'];
    }

    public static function normalizeTheme(?string $theme): string
    {
        return array_key_exists((string) $theme, self::themes())
            ? (string) $theme
            : self::BUILT_IN;
    }

    public static function normalizeTemplate(?string $theme, ?string $template): string
    {
        $theme = self::normalizeTheme($theme);
        $definition = self::themes()[$theme];

        return array_key_exists((string) $template, $definition['templates'])
            ? (string) $template
            : $definition['default_template'];
    }

    public static function homeView(?string $theme, ?string $template): string
    {
        $theme = self::normalizeTheme($theme);
        $template = self::normalizeTemplate($theme, $template);

        return self::themes()[$theme]['views'][$template];
    }
}
