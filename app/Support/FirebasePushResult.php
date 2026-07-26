<?php

namespace App\Support;

final readonly class FirebasePushResult
{
    public const SENT = 'sent';

    public const FAILED = 'failed';

    public const STALE = 'stale';

    public function __construct(
        public string $status,
        public ?string $messageId = null,
        public ?string $errorCode = null,
        public ?string $error = null,
    ) {}

    public static function sent(?string $messageId = null): self
    {
        return new self(self::SENT, messageId: $messageId);
    }

    public static function failed(?string $errorCode, ?string $error): self
    {
        return new self(self::FAILED, errorCode: $errorCode, error: $error);
    }

    public static function stale(?string $errorCode, ?string $error): self
    {
        return new self(self::STALE, errorCode: $errorCode, error: $error);
    }

    public function wasSent(): bool
    {
        return $this->status === self::SENT;
    }

    public function isStale(): bool
    {
        return $this->status === self::STALE;
    }
}
