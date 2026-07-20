<?php

namespace App\Support;

/**
 * Dependency-free Code 128-B barcode renderer producing an inline SVG.
 * Used on printable invoices so the invoice number can be scanned by
 * courier handheld scanners.
 */
class Code128
{
    /**
     * Module widths (bars/spaces alternating, always starting with a bar)
     * for Code 128 symbols 0-106 as defined by ISO/IEC 15417.
     */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    private const START_B = 104;

    private const STOP = 106;

    public static function svg(string $value, int $height = 44, float $module = 1.6): string
    {
        $value = preg_replace('/[^\x20-\x7E]/', '', $value) ?? '';

        if ($value === '') {
            return '';
        }

        $codes = [self::START_B];

        foreach (str_split($value) as $char) {
            $codes[] = ord($char) - 32;
        }

        $checksum = $codes[0];

        foreach ($codes as $index => $code) {
            if ($index > 0) {
                $checksum += $index * $code;
            }
        }

        $codes[] = $checksum % 103;
        $codes[] = self::STOP;

        $bars = [];
        $x = 0.0;

        foreach ($codes as $code) {
            $pattern = self::PATTERNS[$code];

            foreach (str_split($pattern) as $position => $widthDigit) {
                $width = (int) $widthDigit * $module;

                if ($position % 2 === 0) {
                    $bars[] = sprintf('<rect x="%.2f" y="0" width="%.2f" height="%d" />', $x, $width, $height);
                }

                $x += $width;
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %.2f %d" width="%.0f" height="%d" fill="#000" shape-rendering="crispEdges" role="img" aria-label="%s">%s</svg>',
            $x,
            $height,
            $x,
            $height,
            htmlspecialchars($value, ENT_QUOTES),
            implode('', $bars)
        );
    }
}
