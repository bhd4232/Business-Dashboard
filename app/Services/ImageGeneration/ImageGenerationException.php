<?php

namespace App\Services\ImageGeneration;

use RuntimeException;

/**
 * Raised by a provider adapter (or the resolver) when a generation cannot be
 * completed — bad config, an HTTP failure, or a response with no usable
 * image. GenerateImageJob catches this, marks the row `failed` with the
 * message, and notifies the requesting user.
 */
class ImageGenerationException extends RuntimeException
{
}
