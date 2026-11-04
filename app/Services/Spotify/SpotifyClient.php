<?php

namespace App\Services\Spotify;

use App\Models\SpotifyConnection;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SpotifyClient
{
    /**
     * @return list<array<string, mixed>>
     */
    public function savedAlbums(?int $limit = null): array
    {
        $albums = [];
        $offset = 0;

        do {
            $pageLimit = $limit === null ? 50 : min(50, $limit - count($albums));
            $response = $this->request()->get('/me/albums', [
                'limit' => $pageLimit,
                'offset' => $offset,
            ]);

            if ($response->failed()) {
                throw new RuntimeException('Spotify returned HTTP '.$response->status().'.');
            }

            $payload = $response->json();
            $items = data_get($payload, 'items', []);
            $items = array_values(array_filter($items, fn (mixed $item): bool => is_array($item) && is_array($item['album'] ?? null)));
            $albums = array_merge($albums, $items);
            $offset += count($items);
        } while (($limit === null || count($albums) < $limit) && data_get($payload, 'next') !== null && $items !== []);

        return $limit === null ? $albums : array_slice($albums, 0, $limit);
    }

    /**
     * @return array{id: string, external_urls: array<string, string>}
     */
    public function createPrivatePlaylist(string $name, string $description): array
    {
        $response = $this->request()->post('/me/playlists', [
            'name' => $name,
            'description' => $description,
            'public' => false,
            'collaborative' => false,
        ]);

        $playlistId = $response->json('id');

        if ($response->failed() || ! is_string($playlistId) || $playlistId === '') {
            throw new RuntimeException('Spotify playlist creation failed.');
        }

        $externalUrls = $response->json('external_urls');

        return [
            'id' => $playlistId,
            'external_urls' => is_array($externalUrls) ? $externalUrls : [],
        ];
    }

    /** @param list<string> $uris */
    public function addPlaylistItems(string $playlistId, array $uris): void
    {
        foreach (array_chunk($uris, 100) as $chunk) {
            $response = $this->request()->post('/playlists/'.$playlistId.'/items', ['uris' => $chunk]);

            if ($response->failed()) {
                throw new RuntimeException('Spotify playlist items could not be added.');
            }
        }
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(config('services.spotify.api_endpoint'))
            ->withToken($this->accessToken())
            ->acceptJson()
            ->timeout(15)
            ->retry(3, 1000, throw: false);
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
