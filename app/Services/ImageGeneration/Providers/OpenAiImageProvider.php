<?php

namespace App\Services\ImageGeneration\Providers;

use App\Services\ImageGeneration\ImageGenerationException;
use App\Services\ImageGeneration\ImageGenerationRequest;
use App\Services\ImageGeneration\ImageGenerationResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI Images API (`/v1/images/generations`) — the `gpt-image-1` /
 * DALL·E family. `gpt-image-1` always returns base64 (`b64_json`); DALL·E
 * may return a URL, which is downloaded here so the job only ever deals in
 * bytes.
 */
class OpenAiImageProvider extends AbstractImageProvider
{
    public const DEFAULT_ENDPOINT = 'https://api.openai.com/v1/images/generations';

    public const DEFAULT_EDIT_ENDPOINT = 'https://api.openai.com/v1/images/edits';

    /** OpenAI only accepts a fixed set of sizes; anything else snaps to square. */
    protected const ALLOWED_SIZES = ['1024x1024', '1024x1536', '1536x1024', 'auto'];

    protected function providerName(): string
    {
        return 'OpenAI';
    }

    protected function operations(): array
    {
        return [ImageGenerationRequest::OP_GENERATE, ImageGenerationRequest::OP_IMAGE_TO_IMAGE];
    }

    protected function requiresApiKey(): bool
    {
        return true;
    }

    protected function endpointFor(ImageGenerationRequest $request): string
    {
        return $request->endpoint(self::DEFAULT_ENDPOINT);
    }

    protected function editEndpointFor(ImageGenerationRequest $request): string
    {
        return self::DEFAULT_EDIT_ENDPOINT;
    }

    public function generate(ImageGenerationRequest $request): ImageGenerationResult
    {
        $this->assertOperationSupported($request);

        if ($this->requiresApiKey() && blank($request->apiKey)) {
            throw new ImageGenerationException("{$this->providerName()} needs an API key on the provider profile.");
        }

        if ($request->operation === ImageGenerationRequest::OP_IMAGE_TO_IMAGE) {
            return $this->editImage($request);
        }

        $response = $this->send(fn () => $this->http($request)->post($this->endpointFor($request), array_filter([
            'model' => $request->model,
            'prompt' => $request->prompt,
            'n' => max(1, min($request->count, 10)),
            'size' => $this->snapSize($request->size),
        ], fn ($value): bool => $value !== null && $value !== '')));

        $data = (array) $response->json('data', []);

        $images = collect($data)
            ->map(fn ($entry): ?string => $this->decodeBase64(data_get($entry, 'b64_json'))
                ?? $this->downloadUrl(data_get($entry, 'url')))
            ->all();

        return new ImageGenerationResult(
            images: $this->requireImages($images),
            meta: array_filter([
                'provider' => $this->providerName(),
                'model' => $request->model,
                'usage' => $response->json('usage'),
                'revised_prompts' => collect($data)->pluck('revised_prompt')->filter()->values()->all() ?: null,
            ]),
        );
    }

    /**
     * `/v1/images/edits` — regenerate the source image guided by the prompt.
     * gpt-image-1 returns base64 in the same `data[].b64_json` shape.
     */
    protected function editImage(ImageGenerationRequest $request): ImageGenerationResult
    {
        $response = $this->send(fn () => $this->http($request)
            ->attach('image', (string) $request->referenceImage, 'source.png')
            ->post($this->editEndpointFor($request), array_filter([
                'model' => $request->model,
                'prompt' => $request->prompt,
                'n' => max(1, min($request->count, 10)),
                'size' => $this->snapSize($request->size),
            ], fn ($value): bool => $value !== null && $value !== '')));

        $data = (array) $response->json('data', []);

        $images = collect($data)
            ->map(fn ($entry): ?string => $this->decodeBase64(data_get($entry, 'b64_json'))
                ?? $this->downloadUrl(data_get($entry, 'url')))
            ->all();

        return new ImageGenerationResult(
            images: $this->requireImages($images),
            meta: array_filter([
                'provider' => $this->providerName(),
                'model' => $request->model,
                'operation' => ImageGenerationRequest::OP_IMAGE_TO_IMAGE,
                'usage' => $response->json('usage'),
            ]),
        );
    }

    protected function http(ImageGenerationRequest $request): PendingRequest
    {
        $http = Http::timeout($this->timeoutSeconds)->acceptJson();

        return filled($request->apiKey)
            ? $http->withToken((string) $request->apiKey)
            : $http;
    }

    protected function snapSize(string $size): string
    {
        return in_array($size, self::ALLOWED_SIZES, true) ? $size : '1024x1024';
    }

    protected function downloadUrl(mixed $url): ?string
    {
        if (! is_string($url) || ! str_starts_with($url, 'https://')) {
            return null;
        }

        $body = $this->send(fn () => Http::timeout($this->timeoutSeconds)->get($url))->body();

        return $body !== '' ? $body : null;
    }
}
