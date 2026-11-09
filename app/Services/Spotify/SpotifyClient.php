<?php

namespace App\Services\Spotify;

use App\Models\SpotifyConnection;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
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
            $response = $this->get('/me/albums', [
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

    /** @return list<array<string, mixed>> */
    public function playlists(?int $limit = null): array
    {
        return $this->paginate('/me/playlists', $limit, fn (array $item): bool => isset($item['id']));
    }

    /** @param list<string> $ids */
    public function artists(array $ids): array
    {
        $artists = [];

        foreach (array_chunk(array_values(array_unique($ids)), 50) as $chunk) {
            $response = $this->get('/artists', ['ids' => implode(',', $chunk)]);

            if ($response->failed()) {
                throw new RuntimeException('Spotify returned HTTP '.$response->status().'.');
            }

            $artists = array_merge($artists, array_values(array_filter(
                data_get($response->json(), 'artists', []),
                fn (mixed $artist): bool => is_array($artist) && isset($artist['id']),
            )));
        }

        return $artists;
    }

    /** @return list<array<string, mixed>> */
    public function albumTracks(string $albumId): array
    {
        return $this->paginate(
            '/albums/'.$albumId.'/tracks',
            null,
            fn (array $track): bool => isset($track['id']),
        );
    }

    /** @return list<array<string, mixed>> */
    public function playlistItems(string $playlistId): array
    {
        return $this->paginate(
            '/playlists/'.$playlistId.'/items',
            null,
            fn (array $item): bool => is_array($item['track'] ?? null),
        );
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

    /**
     * @param  callable(array<string, mixed>): bool  $filter
     * @return list<array<string, mixed>>
     */
    private function paginate(string $path, ?int $limit, callable $filter): array
    {
        $items = [];
        $offset = 0;

        do {
            $pageLimit = $limit === null ? 50 : min(50, $limit - count($items));
            if ($pageLimit < 1) {
                break;
            }

            $response = $this->get($path, [
                'limit' => $pageLimit,
                'offset' => $offset,
            ]);

            if ($response->failed()) {
                throw new RuntimeException('Spotify returned HTTP '.$response->status().'.');
            }

            $payload = $response->json();
            $pageItems = array_values(array_filter(
                data_get($payload, 'items', []),
                fn (mixed $item): bool => is_array($item) && $filter($item),
            ));
            $items = array_merge($items, $pageItems);
            $offset += count($pageItems);
        } while (($limit === null || count($items) < $limit) && data_get($payload, 'next') !== null && $pageItems !== []);

        return $limit === null ? $items : array_slice($items, 0, $limit);
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(config('services.spotify.api_endpoint'))
            ->withToken($this->accessToken())
            ->acceptJson()
            ->timeout(15)
            ->retry(3, 1000, throw: false);
    }

    /** @param array<string, int|string> $query */
    private function get(string $path, array $query): Response
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $response = $this->request()->get($path, $query);

            if ($response->status() !== 429 || $attempt === 3) {
                return $response;
            }

            $headers = array_change_key_case($response->headers());

            sleep(max(0, (int) ($headers['retry-after'] ?? 1)));
        }

        return $response;
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
