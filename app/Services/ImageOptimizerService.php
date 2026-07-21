<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Re-encodes every admin-uploaded image (product photos, category/logo/slide
 * images, storefront banners) into a compressed WebP file before it's stored,
 * so the storefront never serves an untouched multi-megabyte camera photo.
 *
 * This runs through Filament's FileUpload::saveUploadedFileUsing() hook, so
 * it's the single place this logic lives — individual forms only need to
 * point at it (see App\Filament\Concerns\OptimizesUploadedImages).
 */
class ImageOptimizerService
{
    /** Default longest-edge size for a "full" image (product photo, banner). */
    public const MAX_WIDTH_STANDARD = 1600;

    /** Smaller cap for images that only ever render small (logos, category tiles). */
    public const MAX_WIDTH_COMPACT = 800;

    protected ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver);
    }

    /**
     * Resize (if needed), strip EXIF/metadata, and re-encode as WebP.
     *
     * @return string|null the disk-relative path to persist, or null if the
     *                     temp file is already gone (matches Filament's own
     *                     saveUploadedFile() null-on-missing-file contract)
     */
    public function optimizeAndStore(
        TemporaryUploadedFile $file,
        string $directory,
        string $disk = 'public',
        int $maxWidth = self::MAX_WIDTH_STANDARD,
        int $quality = 82,
    ): ?string {
        try {
            if (! $file->exists()) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        // SVGs are already tiny vectors, and animated GIFs/WebPs would lose
        // their animation if re-encoded as a static WebP frame — leave both
        // exactly as Filament would have stored them, rather than routing
        // them through a raster image library that can't handle either.
        if ($file->getMimeType() === 'image/svg+xml' || $this->isAnimated($file)) {
            return $file->storeAs($directory, $this->randomFilename($file), $disk);
        }

        $image = $this->manager->read($file->getRealPath());

        if ($image->width() > $maxWidth) {
            $image->scaleDown(width: $maxWidth);
        }

        $encoded = (string) $image->toWebp($quality);

        $path = trim($directory, '/').'/'.(string) Str::uuid().'.webp';

        Storage::disk($disk)->put($path, $encoded);

        // R2 does not support S3 object ACLs. Its public/private boundary is
        // configured per bucket, while the local public disk still benefits
        // from an explicit public file mode.
        if (config("filesystems.disks.{$disk}.driver") === 'local') {
            rescue(fn () => Storage::disk($disk)->setVisibility($path, 'public'), report: false);
        }

        return $path;
    }

    protected function isAnimated(TemporaryUploadedFile $file): bool
    {
        $mime = $file->getMimeType();

        if ($mime === 'image/gif') {
            return true;
        }

        if ($mime !== 'image/webp') {
            return false;
        }

        // Cheap animated-WebP sniff: the RIFF container carries an "ANIM"
        // chunk when animated. Reading the first few KB is enough.
        $header = @file_get_contents($file->getRealPath(), false, null, 0, 4096) ?: '';

        return str_contains($header, 'ANIM');
    }

    protected function randomFilename(TemporaryUploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension() ?: $file->extension();

        return (string) Str::uuid().($extension ? '.'.$extension : '');
    }
}
