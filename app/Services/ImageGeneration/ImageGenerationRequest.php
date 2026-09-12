<?php

namespace App\Services\ImageGeneration;

/**
 * The normalized input a provider adapter receives. Assembled by
 * GenerateImageJob from the chosen provider profile plus the user's
 * generation options. Adapters use whichever fields their API speaks —
 * OpenAI wants `size`, Stability and Imagen want `aspectRatio`.
 *
 * `operation` decides which endpoint the adapter hits:
 * - `generate`            → text-to-image (only `prompt` matters)
 * - `image_to_image`      → `referenceImage` + `prompt` + `strength`
 * - `background_removal`  → `referenceImage` only
 */
final class ImageGenerationRequest
{
    public const OP_GENERATE = 'generate';

    public const OP_IMAGE_TO_IMAGE = 'image_to_image';

    public const OP_BACKGROUND_REMOVAL = 'background_removal';

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
        /** self::OP_* — what the adapter should do. */
        public readonly string $operation = self::OP_GENERATE,
        /** Raw bytes of the source image for image_to_image / background_removal. */
        public readonly ?string $referenceImage = null,
        /** image_to_image only: 0 (keep the source) → 1 (ignore it). */
        public readonly float $strength = 0.6,
    ) {}

    public function endpoint(string $default): string
    {
        return $this->baseUrl !== null && trim($this->baseUrl) !== ''
            ? rtrim(trim($this->baseUrl), '/')
            : $default;
    }

    public function requiresReferenceImage(): bool
    {
        return in_array($this->operation, [self::OP_IMAGE_TO_IMAGE, self::OP_BACKGROUND_REMOVAL], true);
    }
}
