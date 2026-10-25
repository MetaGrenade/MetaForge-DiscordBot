<?php

namespace MetaForge\Discord\Services;

use RuntimeException;

class DiscordApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly ?array $body = null,
    ) {
        parent::__construct($message, $status);
    }
}
