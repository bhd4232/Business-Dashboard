<?php

namespace Tests\Feature;

use App\Filament\Concerns\OptimizesUploadedImages;
use App\Services\ImageOptimizerService;
use Filament\Forms\Components\FileUpload;
use Tests\TestCase;

class BrowserImagePrecompressionTest extends TestCase
{
    public function test_standard_image_fields_are_configured_for_native_browser_resize(): void
    {
        $field = BrowserImagePrecompressionProbe::standard();

        $this->assertSame('contain', $field->getAutomaticallyResizeImagesMode());
        $this->assertSame((string) ImageOptimizerService::MAX_WIDTH_STANDARD, $field->getAutomaticallyResizeImagesWidth());
        $this->assertSame((string) ImageOptimizerService::MAX_WIDTH_STANDARD, $field->getAutomaticallyResizeImagesHeight());
        $this->assertFalse($field->shouldAutomaticallyUpscaleImagesWhenResizing());
        $this->assertSame(ImageOptimizerService::MAX_SOURCE_UPLOAD_SIZE_KB, $field->getMaxSize());

        $initializer = $field->getExtraAlpineAttributes()['x-init'] ?? '';

        $this->assertStringContainsString('$watch(\'pond\'', $initializer);
        $this->assertStringContainsString("'image/jpeg'", $initializer);
        $this->assertStringContainsString("'image/png'", $initializer);
        $this->assertStringNotContainsString('image/webp', $initializer);
        $this->assertStringNotContainsString('imageTransformOutputQuality', $initializer);
    }

    public function test_compact_image_fields_use_the_smaller_browser_bound(): void
    {
        $field = BrowserImagePrecompressionProbe::compact();

        $this->assertSame((string) ImageOptimizerService::MAX_WIDTH_COMPACT, $field->getAutomaticallyResizeImagesWidth());
        $this->assertSame((string) ImageOptimizerService::MAX_WIDTH_COMPACT, $field->getAutomaticallyResizeImagesHeight());
        $this->assertSame(ImageOptimizerService::MAX_SOURCE_UPLOAD_SIZE_KB, $field->getMaxSize());
    }
}

class BrowserImagePrecompressionProbe
{
    use OptimizesUploadedImages;

    public static function standard(): FileUpload
    {
        return FileUpload::make('image')
            ->image()
            ->tap(static::browserImagePrecompression());
    }

    public static function compact(): FileUpload
    {
        return FileUpload::make('logo')
            ->image()
            ->tap(static::browserCompactImagePrecompression());
    }
}
