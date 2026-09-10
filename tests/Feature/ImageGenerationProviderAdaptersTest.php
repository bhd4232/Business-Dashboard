<?php

namespace Tests\Feature;

use App\Services\ImageGeneration\ImageGenerationException;
use App\Services\ImageGeneration\ImageGenerationRequest;
use App\Services\ImageGeneration\ImageProviderResolver;
use App\Services\ImageGeneration\Providers\CustomOpenAiCompatibleProvider;
use App\Services\ImageGeneration\Providers\GoogleImageProvider;
use App\Services\ImageGeneration\Providers\OpenAiImageProvider;
use App\Services\ImageGeneration\Providers\StabilityImageProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImageGenerationProviderAdaptersTest extends TestCase
{
    private function pngBytes(int $w = 24, int $h = 24): string
    {
        $image = imagecreatetruecolor($w, $h);
        imagefill($image, 0, 0, imagecolorallocate($image, 12, 34, 56));
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    private function request(array $overrides = []): ImageGenerationRequest
    {
        $get = fn (string $key, mixed $default): mixed => array_key_exists($key, $overrides) ? $overrides[$key] : $default;

        return new ImageGenerationRequest(
            prompt: $get('prompt', 'a red bicycle'),
            model: $get('model', 'gpt-image-1'),
            apiKey: $get('apiKey', 'sk-test'),
            baseUrl: $get('baseUrl', null),
            size: $get('size', '1024x1024'),
            aspectRatio: $get('aspectRatio', '1:1'),
            count: $get('count', 1),
        );
    }

    public function test_openai_adapter_decodes_base64_images(): void
    {
        $png = $this->pngBytes();
        Http::fake([
            'api.openai.com/*' => Http::response(['data' => [['b64_json' => base64_encode($png)]], 'usage' => ['total_tokens' => 5]], 200),
        ]);

        $result = app(OpenAiImageProvider::class)->generate($this->request());

        $this->assertCount(1, $result->images);
        $this->assertSame($png, $result->images[0]);
        $this->assertSame('OpenAI', $result->meta['provider']);

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer sk-test')
            && $request['prompt'] === 'a red bicycle');
    }

    public function test_openai_adapter_downloads_a_url_response(): void
    {
        $png = $this->pngBytes();
        Http::fake([
            'api.openai.com/*' => Http::response(['data' => [['url' => 'https://cdn.example/img.png']]], 200),
            'cdn.example/*' => Http::response($png, 200),
        ]);

        $result = app(OpenAiImageProvider::class)->generate($this->request());

        $this->assertSame($png, $result->images[0]);
    }

    public function test_openai_adapter_turns_an_http_error_into_a_readable_exception(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Invalid API key']], 401)]);

        $this->expectException(ImageGenerationException::class);
        $this->expectExceptionMessageMatches('/Invalid API key/');

        app(OpenAiImageProvider::class)->generate($this->request());
    }

    public function test_openai_adapter_rejects_an_empty_image_list(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['data' => []], 200)]);

        $this->expectException(ImageGenerationException::class);
        $this->expectExceptionMessageMatches('/no image data/');

        app(OpenAiImageProvider::class)->generate($this->request());
    }

    public function test_openai_adapter_requires_an_api_key(): void
    {
        Http::fake();

        $this->expectException(ImageGenerationException::class);
        $this->expectExceptionMessageMatches('/needs an API key/');

        app(OpenAiImageProvider::class)->generate($this->request(['apiKey' => null]));
    }

    public function test_custom_adapter_requires_a_base_url_but_not_a_key(): void
    {
        $png = $this->pngBytes();
        Http::fake(['self-hosted.local/*' => Http::response(['data' => [['b64_json' => base64_encode($png)]]], 200)]);

        $result = app(CustomOpenAiCompatibleProvider::class)->generate($this->request([
            'apiKey' => null,
            'baseUrl' => 'https://self-hosted.local/v1/images/generations',
            'model' => 'sdxl',
        ]));

        $this->assertSame($png, $result->images[0]);
        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://self-hosted.local/'));

        $this->expectException(ImageGenerationException::class);
        app(CustomOpenAiCompatibleProvider::class)->generate($this->request(['baseUrl' => null]));
    }

    public function test_google_adapter_sends_the_key_as_a_header_not_a_query_param(): void
    {
        $png = $this->pngBytes();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'predictions' => [['bytesBase64Encoded' => base64_encode($png), 'mimeType' => 'image/png']],
            ], 200),
        ]);

        $result = app(GoogleImageProvider::class)->generate($this->request([
            'model' => 'imagen-4.0-generate-001',
            'apiKey' => 'goog-key',
        ]));

        $this->assertSame($png, $result->images[0]);
        Http::assertSent(fn ($request): bool => $request->hasHeader('x-goog-api-key', 'goog-key')
            && ! str_contains($request->url(), 'goog-key'));
    }

    public function test_stability_adapter_loops_for_each_variation(): void
    {
        $png = $this->pngBytes();
        Http::fake(['api.stability.ai/*' => Http::response(['image' => base64_encode($png), 'seed' => 7], 200)]);

        $result = app(StabilityImageProvider::class)->generate($this->request([
            'model' => 'core',
            'apiKey' => 'stab-key',
            'count' => 3,
        ]));

        $this->assertCount(3, $result->images);
        Http::assertSentCount(3);
    }

    public function test_resolver_maps_formats_to_adapters(): void
    {
        $resolver = app(ImageProviderResolver::class);

        $this->assertInstanceOf(OpenAiImageProvider::class, $resolver->byFormat('openai'));
        $this->assertInstanceOf(GoogleImageProvider::class, $resolver->byFormat('google'));
        $this->assertInstanceOf(StabilityImageProvider::class, $resolver->byFormat('stability'));
        $this->assertInstanceOf(CustomOpenAiCompatibleProvider::class, $resolver->byFormat('custom'));
        $this->assertFalse($resolver->supports('midjourney'));

        $this->expectException(ImageGenerationException::class);
        $resolver->byFormat('midjourney');
    }
}
