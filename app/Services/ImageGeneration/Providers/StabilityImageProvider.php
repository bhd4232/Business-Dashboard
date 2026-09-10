<?php

namespace App\Services\ImageGeneration\Providers;

use App\Services\ImageGeneration\ImageGenerationException;
use App\Services\ImageGeneration\ImageGenerationRequest;
use App\Services\ImageGeneration\ImageGenerationResult;
use Illuminate\Support\Facades\Http;

/**
 * Stability AI v2beta. Multipart requests, `Accept: application/json` →
 * `{ image: <base64>, seed, finish_reason }`.
 *
 * - generate            → `/v2beta/stable-image/generate/core` (one call per variation)
 * - image_to_image      → `/v2beta/stable-image/generate/sd3` with `mode=image-to-image`
 * - background_removal  → `/v2beta/stable-image/edit/remove-background`
 *
 * The profile's Base URL override only applies to `generate`; the edit
 * endpoints are always the official Stability hosts.
 */
class StabilityImageProvider extends AbstractImageProvider
{
    public const DEFAULT_ENDPOINT = 'https://api.stability.ai/v2beta/stable-image/generate/core';

    public const IMAGE_TO_IMAGE_ENDPOINT = 'https://api.stability.ai/v2beta/stable-image/generate/sd3';

    public const REMOVE_BACKGROUND_ENDPOINT = 'https://api.stability.ai/v2beta/stable-image/edit/remove-background';

    protected const ALLOWED_RATIOS = ['16:9', '1:1', '21:9', '2:3', '3:2', '4:5', '5:4', '9:16', '9:21'];

    protected function providerName(): string
    {
        return 'Stability AI';
    }

    protected function operations(): array
    {
        return [
            ImageGenerationRequest::OP_GENERATE,
            ImageGenerationRequest::OP_IMAGE_TO_IMAGE,
            ImageGenerationRequest::OP_BACKGROUND_REMOVAL,
        ];
    }

    public function generate(ImageGenerationRequest $request): ImageGenerationResult
    {
        $this->assertOperationSupported($request);

        if (blank($request->apiKey)) {
            throw new ImageGenerationException("{$this->providerName()} needs an API key on the provider profile.");
        }

        return match ($request->operation) {
            ImageGenerationRequest::OP_IMAGE_TO_IMAGE => $this->imageToImage($request),
            ImageGenerationRequest::OP_BACKGROUND_REMOVAL => $this->removeBackground($request),
            default => $this->textToImage($request),
        };
    }

    protected function textToImage(ImageGenerationRequest $request): ImageGenerationResult
    {
        $endpoint = $request->endpoint(self::DEFAULT_ENDPOINT);
        $images = [];
        $seeds = [];

        foreach (range(1, max(1, min($request->count, 4))) as $ignored) {
            $response = $this->send(fn () => Http::withToken((string) $request->apiKey)
                ->timeout($this->timeoutSeconds)
                ->acceptJson()
                ->asMultipart()
                ->post($endpoint, [
                    'prompt' => $request->prompt,
                    'aspect_ratio' => $this->snapRatio($request->aspectRatio),
                    'output_format' => 'png',
                    'model' => $request->model ?: 'core',
                ]));

            $images[] = $this->decodeBase64($response->json('image'));
            $seeds[] = $response->json('seed');
        }

        return $this->result($request, $images, $seeds, $request->model ?: 'core');
    }

    protected function imageToImage(ImageGenerationRequest $request): ImageGenerationResult
    {
        $model = $request->model ?: 'sd3.5-medium';
        $images = [];
        $seeds = [];

        foreach (range(1, max(1, min($request->count, 4))) as $ignored) {
            $response = $this->send(fn () => Http::withToken((string) $request->apiKey)
                ->timeout($this->timeoutSeconds)
                ->acceptJson()
                ->attach('image', (string) $request->referenceImage, 'source.png')
                ->post(self::IMAGE_TO_IMAGE_ENDPOINT, [
                    'prompt' => $request->prompt !== '' ? $request->prompt : 'refine this image',
                    'mode' => 'image-to-image',
                    'strength' => max(0.0, min(1.0, $request->strength)),
                    'model' => $model,
                    'output_format' => 'png',
                ]));

            $images[] = $this->decodeBase64($response->json('image'));
            $seeds[] = $response->json('seed');
        }

        return $this->result($request, $images, $seeds, $model);
    }

    protected function removeBackground(ImageGenerationRequest $request): ImageGenerationResult
    {
        $response = $this->send(fn () => Http::withToken((string) $request->apiKey)
            ->timeout($this->timeoutSeconds)
            ->acceptJson()
            ->attach('image', (string) $request->referenceImage, 'source.png')
            ->post(self::REMOVE_BACKGROUND_ENDPOINT, ['output_format' => 'png']));

        return $this->result($request, [$this->decodeBase64($response->json('image'))], [$response->json('seed')], 'remove-background');
    }

    /**
     * @param  array<int, string|null>  $images
     * @param  array<int, mixed>  $seeds
     */
    protected function result(ImageGenerationRequest $request, array $images, array $seeds, string $model): ImageGenerationResult
    {
        return new ImageGenerationResult(
            images: $this->requireImages($images),
            meta: array_filter([
                'provider' => 'stability',
                'model' => $model,
                'operation' => $request->operation,
                'seeds' => array_values(array_filter($seeds, fn ($seed): bool => $seed !== null)) ?: null,
            ]),
        );
    }

    protected function snapRatio(string $ratio): string
    {
        return in_array($ratio, self::ALLOWED_RATIOS, true) ? $ratio : '1:1';
    }
}
