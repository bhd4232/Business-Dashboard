<?php

namespace App\Filament\Concerns;

use App\Services\ImageOptimizerService;
use Closure;
use Filament\Forms\Components\BaseFileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Drop-in for any Filament FileUpload field that accepts images: routes the
 * upload through ImageOptimizerService instead of Filament's default
 * store-as-is behaviour, so every admin/storefront image upload is
 * compressed + converted to WebP without each form re-implementing it.
 *
 * Usage:
 *   FileUpload::make('image')
 *       ->image()
 *       ->disk('public')
 *       ->directory('products')
 *       ->saveUploadedFileUsing(static::optimizeImageUpload()),
 */
trait OptimizesUploadedImages
{
    protected static function optimizeImageUpload(
        int $maxWidth = ImageOptimizerService::MAX_WIDTH_STANDARD,
        int $quality = 82,
    ): Closure {
        return static function (BaseFileUpload $component, TemporaryUploadedFile $file) use ($maxWidth, $quality): ?string {
            return app(ImageOptimizerService::class)->optimizeAndStore(
                $file,
                $component->getDirectory(),
                $component->getDiskName(),
                $maxWidth,
                $quality,
            );
        };
    }

    /** For logos/category tiles — anything that never renders larger than a few hundred px. */
    protected static function optimizeCompactImageUpload(int $quality = 85): Closure
    {
        return static::optimizeImageUpload(ImageOptimizerService::MAX_WIDTH_COMPACT, $quality);
    }
}
