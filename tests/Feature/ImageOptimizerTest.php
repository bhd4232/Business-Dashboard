<?php

namespace Tests\Feature;

use App\Services\ImageOptimizerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Tests\TestCase;

class ImageOptimizerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake(FileUploadConfiguration::disk());
    }

    /** Puts a fake upload where Livewire keeps temporary files and wraps it. */
    protected function makeTemporaryUpload(UploadedFile $file, string $name): TemporaryUploadedFile
    {
        Storage::disk(FileUploadConfiguration::disk())->putFileAs(
            FileUploadConfiguration::path(),
            $file,
            $name,
        );

        return new TemporaryUploadedFile($name, FileUploadConfiguration::disk());
    }

    public function test_large_jpeg_is_resized_and_reencoded_as_webp(): void
    {
        $upload = $this->makeTemporaryUpload(
            UploadedFile::fake()->image('camera-photo.jpg', 2400, 1600),
            'camera-photo.jpg',
        );

        $path = app(ImageOptimizerService::class)->optimizeAndStore($upload, 'products', 'public');

        $this->assertNotNull($path);
        $this->assertStringStartsWith('products/', $path);
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);

        [$width] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame(ImageOptimizerService::MAX_WIDTH_STANDARD, $width);
    }

    public function test_small_image_is_not_upscaled(): void
    {
        $upload = $this->makeTemporaryUpload(
            UploadedFile::fake()->image('small.png', 400, 300),
            'small.png',
        );

        $path = app(ImageOptimizerService::class)->optimizeAndStore($upload, 'categories', 'public');

        $this->assertStringEndsWith('.webp', $path);
        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame(400, $width);
        $this->assertSame(300, $height);
    }

    public function test_compact_width_cap_applies_for_logos(): void
    {
        $upload = $this->makeTemporaryUpload(
            UploadedFile::fake()->image('logo.png', 1200, 400),
            'logo.png',
        );

        $path = app(ImageOptimizerService::class)->optimizeAndStore(
            $upload,
            'storefront/logos',
            'public',
            ImageOptimizerService::MAX_WIDTH_COMPACT,
        );

        [$width] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame(ImageOptimizerService::MAX_WIDTH_COMPACT, $width);
    }

    public function test_svg_is_stored_untouched(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>';
        $upload = $this->makeTemporaryUpload(
            UploadedFile::fake()->createWithContent('logo.svg', $svg),
            'logo.svg',
        );

        $path = app(ImageOptimizerService::class)->optimizeAndStore($upload, 'storefront/logos', 'public');

        $this->assertStringEndsWith('.svg', $path);
        $this->assertSame($svg, Storage::disk('public')->get($path));
    }

    public function test_animated_gif_is_stored_untouched(): void
    {
        // A 1x1 GIF: re-encoding any GIF is skipped to preserve animations.
        $gif = base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');
        $upload = $this->makeTemporaryUpload(
            UploadedFile::fake()->createWithContent('anim.gif', $gif),
            'anim.gif',
        );

        $path = app(ImageOptimizerService::class)->optimizeAndStore($upload, 'products', 'public');

        $this->assertStringEndsWith('.gif', $path);
        $this->assertSame($gif, Storage::disk('public')->get($path));
    }
}
