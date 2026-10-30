<?php

namespace App\Services\Spotify;

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
        if (! config('services.spotify.access_token')) {
            throw new RuntimeException('Spotify access token is not configured.');
        }

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

    private function request(): PendingRequest
    {
        return Http::baseUrl(config('services.spotify.api_endpoint'))
            ->withToken(config('services.spotify.access_token'))
            ->acceptJson()
            ->timeout(15)
            ->retry(3, 1000, throw: false);
    }
}
