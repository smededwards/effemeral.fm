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
                throw new RuntimeException($this->errorMessage($response));
            }

            $payload = $response->json();
            $pageItems = data_get($payload, 'items', []);
            $pageItems = is_array($pageItems) ? array_values($pageItems) : [];
            $items = array_values(array_filter($pageItems, fn (mixed $item): bool => is_array($item) && is_array($item['album'] ?? null)));
            $albums = array_merge($albums, $items);
            $offset += count($pageItems);
        } while (($limit === null || count($albums) < $limit) && data_get($payload, 'next') !== null && $pageItems !== []);

        return $limit === null ? $albums : array_slice($albums, 0, $limit);
    }

    /** @return list<array<string, mixed>> */
    public function likedTracks(?int $limit = null): array
    {
        return $this->paginate(
            '/me/tracks',
            $limit,
            fn (array $item): bool => is_array($item['track'] ?? null) && isset($item['track']['id']),
        );
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
                throw new RuntimeException($this->errorMessage($response));
            }

            $artists = array_merge($artists, array_values(array_filter(
                data_get($response->json(), 'artists', []),
                fn (mixed $artist): bool => is_array($artist) && isset($artist['id']),
            )));
        }

        return $artists;
    }

    /** @return list<array<string, mixed>> */
    public function searchAlbums(string $artistName, string $albumTitle): array
    {
        $response = $this->get('/search', [
            'q' => 'album:"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $albumTitle).'" artist:"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $artistName).'"',
            'type' => 'album',
            'limit' => 10,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Spotify album search failed (HTTP '.$response->status().').');
        }

        $items = data_get($response->json(), 'albums.items', []);

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
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
        $items = $this->paginate(
            '/playlists/'.$playlistId.'/items',
            null,
            fn (array $item): bool => is_array($item['item'] ?? $item['track'] ?? null),
        );

        return array_map(fn (array $item): array => is_array($item['item'] ?? null)
            ? [...$item, 'track' => $item['item']]
            : $item, $items);
    }

    /** @return array{id: string, name: string, snapshot_id: string|null, public: bool|null, external_urls: array<string, mixed>} */
    public function playlistDetails(string $playlistId): array
    {
        $response = $this->get('/playlists/'.$playlistId, []);

        if ($response->failed()) {
            throw new RuntimeException('Spotify playlist details could not be read (HTTP '.$response->status().'). Reconnect Spotify if access was denied.');
        }

        $data = $response->json();
        $id = data_get($data, 'id');
        $name = data_get($data, 'name');
        $snapshotId = data_get($data, 'snapshot_id');
        $externalUrls = data_get($data, 'external_urls', []);

        if (! is_string($id) || ! is_string($name) || ! is_string($snapshotId)) {
            throw new RuntimeException('Spotify returned incomplete playlist details. Refresh the imported playlist and try again.');
        }

        return [
            'id' => $id,
            'name' => $name,
            'snapshot_id' => $snapshotId,
            'public' => is_bool(data_get($data, 'public')) ? data_get($data, 'public') : null,
            'external_urls' => is_array($externalUrls) ? $externalUrls : [],
        ];
    }

    public function updatePlaylistName(string $playlistId, string $name): void
    {
        $response = $this->put('/playlists/'.$playlistId, ['name' => $name]);

        if ($response->failed()) {
            throw new RuntimeException('Spotify playlist name could not be updated (HTTP '.$response->status().'). Reconnect Spotify if access was denied.');
        }
    }

    /** @param list<string> $uris */
    public function removePlaylistItems(string $playlistId, array $uris, ?string $snapshotId): ?string
    {
        foreach (array_chunk($uris, 100) as $chunk) {
            $payload = ['items' => array_map(fn (string $uri): array => ['uri' => $uri], $chunk)];
            if ($snapshotId !== null) {
                $payload['snapshot_id'] = $snapshotId;
            }

            $response = $this->sendWithRateLimit(
                fn (): Response => $this->request()->send('DELETE', '/playlists/'.$playlistId.'/items', ['json' => $payload]),
            );

            if ($response->failed()) {
                throw new RuntimeException('Spotify playlist tracks could not be removed (HTTP '.$response->status().'). Recheck the preview and Spotify access before retrying.');
            }

            $snapshotId = $response->json('snapshot_id', $snapshotId);
        }

        return is_string($snapshotId) ? $snapshotId : null;
    }

    /**
     * @return array{id: string, external_urls: array<string, string>}
     */
    public function createPrivatePlaylist(string $name, string $description): array
    {
        $response = $this->post('/me/playlists', [
            'name' => $name,
            'description' => $description,
            'public' => false,
            'collaborative' => false,
        ]);

        $playlistId = $response->json('id');

        if ($response->failed()) {
            throw new RuntimeException('Spotify playlist creation failed (HTTP '.$response->status().'). Reconnect Spotify if playlist permissions are missing.');
        }

        if (! is_string($playlistId) || $playlistId === '') {
            throw new RuntimeException('Spotify did not return the new playlist ID. Check Spotify before retrying.');
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
            $response = $this->post('/playlists/'.$playlistId.'/items', ['uris' => $chunk]);

            if ($response->failed()) {
                throw new RuntimeException('Spotify tracks could not be added (HTTP '.$response->status().'). Verify playlist access and reconnect Spotify if permissions changed.');
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
                throw new RuntimeException($this->errorMessage($response));
            }

            $payload = $response->json();
            $rawItems = data_get($payload, 'items', []);
            $rawItems = is_array($rawItems) ? array_values($rawItems) : [];
            $pageItems = array_values(array_filter(
                $rawItems,
                fn (mixed $item): bool => is_array($item) && $filter($item),
            ));
            $items = array_merge($items, $pageItems);
            $offset += count($rawItems);
        } while (($limit === null || count($items) < $limit) && data_get($payload, 'next') !== null && $rawItems !== []);

        return $limit === null ? $items : array_slice($items, 0, $limit);
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(config('services.spotify.api_endpoint'))
            ->withToken($this->accessToken())
            ->acceptJson()
            ->timeout(15)
            ->retry(3, 1000, function (Throwable $exception): bool {
                return !($exception instanceof RequestException && $exception->response?->status() === 429);
            }, throw: false);
    }

    /** @param array<string, int|string> $query */
    private function get(string $path, array $query): Response
    {
        return $this->sendWithRateLimit(fn (): Response => $this->request()->get($path, $query));
    }

    /** @param array<string, mixed> $payload */
    private function post(string $path, array $payload): Response
    {
        return $this->sendWithRateLimit(fn (): Response => $this->request()->post($path, $payload));
    }

    /** @param array<string, mixed> $payload */
    private function put(string $path, array $payload): Response
    {
        return $this->sendWithRateLimit(fn (): Response => $this->request()->put($path, $payload));
    }

    /** @param Closure(): Response $request */
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

    private function errorMessage(Response $response): string
    {
        return $response->status() === 429
            ? 'Spotify rate limit reached. Try again later.'
            : 'Spotify returned HTTP '.$response->status().'.';
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
