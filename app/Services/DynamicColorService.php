<?php

namespace App\Services;

use Filament\Support\Colors\Color;

/**
 * Expands a single hex color into the 50–950 Filament/Tailwind shade ladder
 * used by the admin panel's CSS custom properties (--primary-50 .. --primary-950).
 *
 * Delegates the actual shade math to Filament's own OKLCH-based
 * Color::generatePalette(), which is what Filament's compiled CSS already
 * expects — reimplementing HSL shade math by hand would risk producing
 * colors that don't match how Filament renders a static Color::* palette.
 */
class DynamicColorService
{
    public const DEFAULT_COLOR = '#F59E0B';

    /**
     * @return array<string, string> shade (50..950) => CSS color value
     */
    public function generateShades(string $hex): array
    {
        $hex = $this->normalizeHex($hex);

        return Color::generatePalette($hex);
    }

    /**
     * @return array<string, string> CSS custom property declarations, e.g. "--primary-500: oklch(...)"
     */
    public function cssVariables(string $hex, string $prefix = 'primary'): array
    {
        $declarations = [];

        foreach ($this->generateShades($hex) as $shade => $color) {
            $declarations[] = "--{$prefix}-{$shade}: {$color}";
        }

        return $declarations;
    }

    protected function normalizeHex(string $hex): string
    {
        $hex = trim($hex);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $hex) ? $hex : self::DEFAULT_COLOR;
    }
}
