<?php

namespace App\Services\Spotify;

use App\Models\SpotifyConnection;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Handles authenticated Spotify HTTP requests and provider rate limits.
 */
class SpotifyApiTransport
{
    /**
     * Send a GET request to Spotify.
     *
     * @param  string  $path  The Spotify API path.
     * @param  array<string, int|string>  $query  The request parameters.
     */
    public function get(string $path, array $query): Response
    {
        return $this->sendWithRateLimit(fn (): Response => $this->request()->get($path, $query));
    }

    /**
     * Send a POST request to Spotify.
     *
     * @param  string  $path  The Spotify API path.
     * @param  array<string, mixed>  $payload  The request body.
     */
    public function post(string $path, array $payload): Response
    {
        return $this->sendWithRateLimit(fn (): Response => $this->request()->post($path, $payload));
    }

    /**
     * Send a PUT request to Spotify.
     *
     * @param  string  $path  The Spotify API path.
     * @param  array<string, mixed>  $payload  The request body.
     */
    public function put(string $path, array $payload): Response
    {
        return $this->sendWithRateLimit(fn (): Response => $this->request()->put($path, $payload));
    }

    /**
     * Send a DELETE request to Spotify.
     *
     * @param  string  $path  The Spotify API path.
     * @param  array<string, mixed>  $payload  The request body.
     */
    public function delete(string $path, array $payload): Response
    {
        return $this->sendWithRateLimit(fn (): Response => $this->request()->send('DELETE', $path, ['json' => $payload]));
    }

    /**
     * Describe a failed Spotify response.
     */
    public function errorMessage(Response $response): string
    {
        return $response->status() === 429
            ? 'Spotify rate limit reached. Try again later.'
            : 'Spotify returned HTTP '.$response->status().'.';
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(config('services.spotify.api_endpoint'))
            ->withToken($this->accessToken())
            ->acceptJson()
            ->timeout(15)
            ->retry(3, 1000, function (Throwable $exception): bool {
                return $exception instanceof RequestException
                    ? $exception->response->status() !== 429
                    : true;
            }, throw: false);
    }

    /**
     * Retry recoverable provider throttling while surfacing quota exhaustion.
     *
     * @param  Closure(): Response  $request  The Spotify request callback.
     */
    private function sendWithRateLimit(Closure $request): Response
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $response = $request();

            if ($response->status() !== 429) {
                return $response;
            }

            if ($response->json('error.reason') === 'QUOTA_EXCEEDED') {
                throw new SpotifyRateLimitException('Spotify quota has been exceeded. Try again later.', false);
            }

            if ($attempt === 3) {
                throw new SpotifyRateLimitException($this->errorMessage($response));
            }

            $headers = array_change_key_case($response->headers());
            $retryAfter = (int) ($headers['retry-after'] ?? 0);
            sleep($retryAfter > 0 ? $retryAfter : 2 ** ($attempt - 1));
        }

        throw new SpotifyRateLimitException('Spotify rate limit could not be cleared.');
    }

    private function accessToken(): string
    {
        $connection = SpotifyConnection::query()->latest()->first();

        if ($connection === null) {
            $token = config('services.spotify.access_token');

            if (! is_string($token) || $token === '') {
                throw new RuntimeException('Spotify account is not connected.');
            }

            return $token;
        }

        if ($connection->expires_at === null || CarbonImmutable::parse((string) $connection->expires_at)->isFuture()) {
            return $connection->access_token;
        }

        $response = Http::asForm()->acceptJson()->post(config('services.spotify.token_endpoint'), [
            'grant_type' => 'refresh_token',
            'refresh_token' => $connection->refresh_token,
            'client_id' => config('services.spotify.client_id'),
            'client_secret' => config('services.spotify.client_secret'),
        ]);

        if ($response->failed() || ! is_string($response->json('access_token'))) {
            throw new RuntimeException('Spotify token refresh failed.');
        }

        $connection->update([
            'access_token' => $response->json('access_token'),
            'expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
            'refresh_token' => $response->json('refresh_token', $connection->refresh_token),
        ]);

        return $connection->access_token;
    }
}
