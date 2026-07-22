<?php

namespace App\Services\Meta;

use RuntimeException;

class MetaGraphException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $graphCode = null,
        public readonly ?int $httpStatus = null,
    ) {
        parent::__construct($message);
    }
}
