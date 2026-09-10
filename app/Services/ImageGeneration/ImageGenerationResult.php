<?php

namespace App\Services\ImageGeneration;

/**
 * What a provider adapter returns: the raw image bytes for each variation
 * (already decoded from base64 / downloaded from any temporary URL by the
 * adapter), plus a small metadata array for `generated_images.provider_response`
 * — never the binary, just usage / model / seed / revised-prompt style fields
 * useful for debugging and the future cost dashboard.
 */
final class ImageGenerationResult
{
    /**
     * @param  array<int, string>  $images  raw binary image data, one entry per variation
     * @param  array<string, mixed>  $meta   non-binary provider response fields
     */
    public function __construct(
        public readonly array $images,
        public readonly array $meta = [],
    ) {}
}
