<?php

namespace App\Services\Spotify;

use RuntimeException;

class SpotifyRateLimitException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = true,
    ) {
        parent::__construct($message);
    }
}
