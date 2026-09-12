<?php

namespace App\Services\ImageGeneration\Providers;

use App\Services\ImageGeneration\ImageGenerationException;
use App\Services\ImageGeneration\ImageGenerationRequest;
use App\Services\ImageGeneration\ImageGenerationResult;
use Illuminate\Support\Facades\Http;

/**
 * Google Imagen via the Generative Language API `:predict` endpoint. The
 * API key goes in the `x-goog-api-key` header (never the URL) per the
 * app-wide "no credentials in query strings" rule.
 *
 * Request:  { instances: [{ prompt }], parameters: { sampleCount, aspectRatio } }
 * Response: { predictions: [{ bytesBase64Encoded, mimeType }] }
 */
class GoogleImageProvider extends AbstractImageProvider
{
    public const DEFAULT_BASE = 'https://generativelanguage.googleapis.com/v1beta/models';

    protected const ALLOWED_RATIOS = ['1:1', '3:4', '4:3', '9:16', '16:9'];

    protected function providerName(): string
    {
        return 'Google Imagen';
    }

    public function generate(ImageGenerationRequest $request): ImageGenerationResult
    {
        $this->assertOperationSupported($request);

        if (blank($request->apiKey)) {
            throw new ImageGenerationException("{$this->providerName()} needs an API key on the provider profile.");
        }

        if (blank($request->model)) {
            throw new ImageGenerationException("{$this->providerName()} needs a model (e.g. imagen-4.0-generate-001) on the provider profile.");
        }

        $response = $this->send(fn () => Http::withHeaders(['x-goog-api-key' => (string) $request->apiKey])
            ->timeout($this->timeoutSeconds)
            ->acceptJson()
            ->post($this->endpoint($request), [
                'instances' => [['prompt' => $request->prompt]],
                'parameters' => [
                    'sampleCount' => max(1, min($request->count, 4)),
                    'aspectRatio' => $this->snapRatio($request->aspectRatio),
                ],
            ]));

        $predictions = (array) $response->json('predictions', []);

        $images = collect($predictions)
            ->map(fn ($entry): ?string => $this->decodeBase64(
                data_get($entry, 'bytesBase64Encoded') ?? data_get($entry, 'image.bytesBase64Encoded')
            ))
            ->all();

        return new ImageGenerationResult(
            images: $this->requireImages($images),
            meta: array_filter([
                'provider' => 'google',
                'model' => $request->model,
                'mime_types' => collect($predictions)->pluck('mimeType')->filter()->unique()->values()->all() ?: null,
            ]),
        );
    }

    protected function endpoint(ImageGenerationRequest $request): string
    {
        if (filled($request->baseUrl)) {
            return rtrim(trim((string) $request->baseUrl), '/');
        }

        return self::DEFAULT_BASE.'/'.rawurlencode($request->model).':predict';
    }

    protected function snapRatio(string $ratio): string
    {
        return in_array($ratio, self::ALLOWED_RATIOS, true) ? $ratio : '1:1';
    }
}
