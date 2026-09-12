<?php

namespace App\Services\ImageGeneration\Providers;

use App\Services\ImageGeneration\ImageGenerationException;
use App\Services\ImageGeneration\ImageGenerationRequest;

/**
 * Any third-party or self-hosted endpoint that speaks the OpenAI Images
 * request/response shape. Identical to OpenAiImageProvider except the
 * profile's `base_url` is mandatory (there is no default endpoint) and a
 * blank API key is allowed for keyless self-hosted servers.
 */
class CustomOpenAiCompatibleProvider extends OpenAiImageProvider
{
    protected function providerName(): string
    {
        return 'Custom image provider';
    }

    protected function requiresApiKey(): bool
    {
        return false;
    }

    protected function endpointFor(ImageGenerationRequest $request): string
    {
        if (blank($request->baseUrl)) {
            throw new ImageGenerationException('A custom image provider needs a Base URL on its profile.');
        }

        return rtrim(trim((string) $request->baseUrl), '/');
    }

    /**
     * The profile's Base URL is its text-to-image endpoint; the edits
     * endpoint is derived by swapping the OpenAI path segment
     * (`.../images/generations` → `.../images/edits`), or appended when the
     * URL does not carry that segment.
     */
    protected function editEndpointFor(ImageGenerationRequest $request): string
    {
        $base = $this->endpointFor($request);

        if (stripos($base, '/generations') !== false) {
            return preg_replace('#/generations(/?$)#i', '/edits$1', $base);
        }

        return $base.'/edits';
    }
}
