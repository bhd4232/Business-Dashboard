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

    /**
     * Real banner/hero image dimensions per theme, sourced from the actual
     * homepage view markup (not guessed): Built-in and Marketplace Pro both
     * render slides through `storefront.partials.image-banner` (full-width
     * banner, ~3:1); Noor Solar shows only the first slide as a 4:3 hero
     * visual with no mobile variant (see `themes/noor-solar/home.blade.php`).
     *
     * @var array<string, array{desktop: array{width: int, height: int, note: string}, mobile: array{width: int, height: int, note: string}|null}>
     */
    public const BANNER_SPECS = [
        self::BUILT_IN => [
            'desktop' => [
                'width' => 1920,
                'height' => 640,
                'note' => 'Extra-wide banner, at least 1920×640px (about 3:1). It fills the viewport width and is cropped to one-third of the desktop screen height.',
            ],
            'mobile' => [
                'width' => 900,
                'height' => 320,
                'note' => 'Optional. Use a wide mobile banner, at least 900×320px. It is cropped to one-sixth of the mobile screen height.',
            ],
        ],
        self::MARKETPLACE_PRO => [
            'desktop' => [
                'width' => 1920,
                'height' => 640,
                'note' => 'Extra-wide banner, at least 1920×640px (about 3:1), same as Built-in — every Marketplace Pro homepage template shares this banner slot.',
            ],
            'mobile' => [
                'width' => 900,
                'height' => 320,
                'note' => 'Optional. Use a wide mobile banner, at least 900×320px. It is cropped to one-sixth of the mobile screen height.',
            ],
        ],
        self::NOOR_SOLAR => [
            'desktop' => [
                'width' => 1200,
                'height' => 900,
                'note' => 'Hero visual, at least 1200×900px (4:3). Noor Solar shows only the first slide as a single hero image, not a carousel.',
            ],
            'mobile' => null,
        ],
    ];

    public static function bannerSpec(?string $theme): array
    {
        return self::BANNER_SPECS[self::normalizeTheme($theme)] ?? self::BANNER_SPECS[self::BUILT_IN];
    }

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
                'layout' => 'storefront.layout',
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
                // No distinct Marketplace Pro header/footer exists yet — this
                // theme currently shares the default layout's header/footer,
                // only its homepage content differs. Set this to a theme-
                // specific view (e.g. 'storefront.themes.marketplace-pro.layout')
                // once one is designed; every storefront page will pick it up
                // automatically via layoutView(), no other file needs to change.
                'layout' => 'storefront.layout',
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
                'layout' => 'storefront.layout',
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

    /**
     * The Blade view every storefront page (home and otherwise) extends for
     * its header/footer. Falls back to the default 'storefront.layout' for
     * any theme that hasn't defined its own — so pointing a theme at a
     * custom layout is a one-line change here, not a per-view migration.
     */
    public static function layoutView(?string $theme): string
    {
        return self::themes()[self::normalizeTheme($theme)]['layout'] ?? 'storefront.layout';
    }
}
