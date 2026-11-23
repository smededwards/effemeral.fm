<?php

namespace App\Services\Spotify;

use RuntimeException;

/**
 * Signals a Spotify quota response that should not be retried immediately.
 */
class SpotifyRateLimitException extends RuntimeException
{
    /**
     * @param  string  $message  The provider error message.
     * @param  bool  $retryable  Whether the caller may retry this request.
     */
    public function __construct(
        string $message,
        public readonly bool $retryable = true,
    ) {
        parent::__construct($message);
    }
}
