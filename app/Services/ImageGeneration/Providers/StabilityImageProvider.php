<?php

namespace App\Services\ImageGeneration\Providers;

use App\Services\ImageGeneration\ImageGenerationException;
use App\Services\ImageGeneration\ImageGenerationRequest;
use App\Services\ImageGeneration\ImageGenerationResult;
use Illuminate\Support\Facades\Http;

/**
 * Stability AI v2beta text-to-image (`/v2beta/stable-image/generate/core`).
 * Multipart request, one image per call — the adapter loops for the
 * requested variation count. `Accept: application/json` returns
 * `{ image: <base64>, seed, finish_reason }`.
 */
class StabilityImageProvider extends AbstractImageProvider
{
    public const DEFAULT_ENDPOINT = 'https://api.stability.ai/v2beta/stable-image/generate/core';

    protected const ALLOWED_RATIOS = ['16:9', '1:1', '21:9', '2:3', '3:2', '4:5', '5:4', '9:16', '9:21'];

    protected function providerName(): string
    {
        return 'Stability AI';
    }

    public function generate(ImageGenerationRequest $request): ImageGenerationResult
    {
        if (blank($request->apiKey)) {
            throw new ImageGenerationException("{$this->providerName()} needs an API key on the provider profile.");
        }

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

        return new ImageGenerationResult(
            images: $this->requireImages($images),
            meta: array_filter([
                'provider' => 'stability',
                'model' => $request->model ?: 'core',
                'seeds' => array_values(array_filter($seeds, fn ($seed): bool => $seed !== null)) ?: null,
            ]),
        );
    }

    protected function snapRatio(string $ratio): string
    {
        return in_array($ratio, self::ALLOWED_RATIOS, true) ? $ratio : '1:1';
    }
}
