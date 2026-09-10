<?php

namespace App\Services\ImageGeneration;

use RuntimeException;

/**
 * Raised by GeneratedImageAttacher when a finished image cannot be attached
 * to a target record — the generation is not finished, the chosen output no
 * longer exists, the target's product gallery is full, or the target belongs
 * to another company. The ImageGeneration page catches this and shows the
 * message as a notification.
 */
class ImageAttachmentException extends RuntimeException
{
}
