<?php

namespace App\Filament\Concerns;

use App\Models\Media;
use App\Services\ImageOptimizerService;
use Closure;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Drop-in for any Filament FileUpload field that accepts images: routes the
 * upload through ImageOptimizerService instead of Filament's default
 * store-as-is behaviour, so every admin/storefront image upload is
 * compressed + converted to WebP without each form re-implementing it.
 *
 * JPEG and PNG sources are also resized in the browser before Livewire starts
 * its temporary upload. The server remains the authoritative final WebP and
 * metadata-cleanup step; SVG, GIF, and WebP sources deliberately bypass the
 * browser transform so vectors and animations cannot be damaged.
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
        return static function (BaseFileUpload $component, TemporaryUploadedFile $file, $record = null) use ($maxWidth, $quality): ?string {
            $path = app(ImageOptimizerService::class)->optimizeAndStore(
                $file,
                $component->getDirectory(),
                $component->getDiskName(),
                $maxWidth,
                $quality,
            );

            // Every image saved through this shared upload path also shows
            // up in the company's Media Hub, so it can be reused on another
            // field later via "Select From Media" without re-uploading.
            // Best-effort: a Media Hub bookkeeping failure (e.g. no company
            // can be resolved yet on a brand-new, unsaved record) must never
            // block the image upload itself.
            if ($path !== null) {
                rescue(fn () => Media::recordUpload($component->getDiskName(), $path, $record, file: $file), report: false);
            }

            return $path;
        };
    }

    /** For logos/category tiles — anything that never renders larger than a few hundred px. */
    protected static function optimizeCompactImageUpload(int $quality = 85): Closure
    {
        return static::optimizeImageUpload(ImageOptimizerService::MAX_WIDTH_COMPACT, $quality);
    }

    /**
     * Configure Filament's built-in FilePond transform before a raster image
     * is sent to Livewire. FilePond validates source size before transforming,
     * so its source ceiling must match the Livewire/PHP temporary-upload limit.
     *
     * Filament does not expose FilePond's image-transform MIME filter as a
     * PHP method. The component's native `pond` object is available after its
     * async initialization, so a scoped Alpine watcher safely sets that filter
     * without changing any non-image upload field.
     *
     * @return Closure(FileUpload): FileUpload
     */
    protected static function browserImagePrecompression(
        int $maxDimension = ImageOptimizerService::MAX_WIDTH_STANDARD,
    ): Closure {
        return static function (FileUpload $component) use ($maxDimension): FileUpload {
            return $component
                // `contain` bounds the longest edge without cropping and the
                // explicit no-upscale guard preserves already-small images.
                ->automaticallyResizeImagesMode('contain')
                ->automaticallyResizeImagesToWidth((string) $maxDimension)
                ->automaticallyResizeImagesToHeight((string) $maxDimension)
                ->automaticallyUpscaleImagesWhenResizing(false)
                ->maxSize(ImageOptimizerService::MAX_SOURCE_UPLOAD_SIZE_KB)
                ->extraAlpineAttributes([
                    'x-init' => <<<'JS'
                        $watch('pond', (pond) => {
                            if (! pond) {
                                return
                            }

                            pond.setOptions({
                                imageTransformImageFilter: (file) => [
                                    'image/jpeg',
                                    'image/png',
                                ].includes((file.type || '').toLowerCase()),
                            })
                        })
                    JS,
                ], merge: true);
        };
    }

    /** @return Closure(FileUpload): FileUpload */
    protected static function browserCompactImagePrecompression(): Closure
    {
        return static::browserImagePrecompression(ImageOptimizerService::MAX_WIDTH_COMPACT);
    }
}
