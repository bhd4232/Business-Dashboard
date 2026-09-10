<?php

namespace App\Services\ImageGeneration;

/**
 * The normalized input a provider adapter receives. Assembled by
 * GenerateImageJob from the chosen provider profile plus the user's
 * generation options. Adapters use whichever fields their API speaks —
 * OpenAI wants `size`, Stability and Imagen want `aspectRatio`.
 */
final class ImageGenerationRequest
{
    public function __construct(
        public readonly string $prompt,
        public readonly string $model,
        public readonly ?string $apiKey,
        public readonly ?string $baseUrl,
        /** Pixel size, "WIDTHxHEIGHT" (e.g. "1024x1024"). */
        public readonly string $size = '1024x1024',
        /** Aspect ratio, "W:H" (e.g. "1:1", "16:9"). */
        public readonly string $aspectRatio = '1:1',
        /** Number of variations requested. Adapters that cannot batch loop internally. */
        public readonly int $count = 1,
    ) {}

    public function endpoint(string $default): string
    {
        return $this->baseUrl !== null && trim($this->baseUrl) !== ''
            ? rtrim(trim($this->baseUrl), '/')
            : $default;
    }
}
