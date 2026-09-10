<?php

namespace App\Services\ImageGeneration;

/**
 * Thin contract for a text-to-image provider. Intentionally not built on
 * App\Services\Crm\AiLlmClient's `chat()` shape — image APIs take a prompt +
 * size + variation count and return binary/base64 image data, which is
 * different enough that force-fitting the chat shape would hurt more than it
 * helps.
 *
 * Every adapter is `Http::fake()`-mocked in tests — never called live there,
 * matching the existing rule for AiLlmClient.
 */
interface ImageGenerationClient
{
    /**
     * @throws ImageGenerationException on bad config, HTTP failure, or a
     *                                  response with no usable image
     */
    public function generate(ImageGenerationRequest $request): ImageGenerationResult;
}
