<?php

namespace App\Services\Spotify;

use RuntimeException;

/**
 * Wraps the Spotify Web API with authentication, pagination, and rate limits.
 */
class SpotifyClient
{
    public function __construct(private SpotifyApiTransport $transport) {}

    /**
     * @return list<array<string, mixed>>
     */
    /**
     * Fetch saved albums, optionally capped at the requested item count.
     *
     * @param  int|null  $limit  The maximum number of albums to return.
     * @return list<array<string, mixed>> The Spotify saved-album payloads.
     */
    public function savedAlbums(?int $limit = null): array
    {
        $albums = [];
        $offset = 0;

        do {
            $pageLimit = $limit === null ? 50 : min(50, $limit - count($albums));
            $response = $this->transport->get('/me/albums', [
                'limit' => $pageLimit,
                'offset' => $offset,
            ]);

            if ($response->failed()) {
                throw new RuntimeException($this->transport->errorMessage($response));
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

    /**
     * Fetch one resumable saved-album page and its next offset.
     *
     * @param  int  $offset  The first Spotify item to request.
     * @param  int  $limit  The maximum page size.
     * @return array{items: list<array<string, mixed>>, next_offset: int, total: int} The page and progress values.
     */
    public function savedAlbumsPage(int $offset = 0, int $limit = 50): array
    {
        $response = $this->transport->get('/me/albums', [
            'limit' => min(50, max(1, $limit)),
            'offset' => max(0, $offset),
        ]);

        if ($response->failed()) {
            throw new RuntimeException($this->transport->errorMessage($response));
        }

        $payload = $response->json();
        $rawItems = data_get($payload, 'items', []);
        $rawItems = is_array($rawItems) ? array_values($rawItems) : [];

        return [
            'items' => array_values(array_filter($rawItems, fn (mixed $item): bool => is_array($item) && is_array($item['album'] ?? null))),
            'next_offset' => $offset + count($rawItems),
            'total' => (int) data_get($payload, 'total', $offset + count($rawItems)),
        ];
    }

    /**
     * Fetch liked tracks, optionally capped at the requested item count.
     *
     * @param  int|null  $limit  The maximum number of tracks to return.
     * @return list<array<string, mixed>> The Spotify liked-track payloads.
     */
    public function likedTracks(?int $limit = null): array
    {
        return $this->paginate(
            '/me/tracks',
            $limit,
            fn (array $item): bool => is_array($item['track'] ?? null) && isset($item['track']['id']),
        );
    }

    /**
     * Fetch imported Spotify playlists, optionally capped at the requested count.
     *
     * @param  int|null  $limit  The maximum number of playlists to return.
     * @return list<array<string, mixed>> The Spotify playlist payloads.
     */
    public function playlists(?int $limit = null): array
    {
        return $this->paginate('/me/playlists', $limit, fn (array $item): bool => isset($item['id']));
    }

    /**
     * Fetch one resumable playlist page and its next offset.
     *
     * @param  int  $offset  The first Spotify playlist to request.
     * @param  int  $limit  The maximum page size.
     * @return array{items: list<array<string, mixed>>, next_offset: int, total: int} The page and progress values.
     */
    public function playlistsPage(int $offset = 0, int $limit = 50): array
    {
        $response = $this->transport->get('/me/playlists', [
            'limit' => min(50, max(1, $limit)),
            'offset' => max(0, $offset),
        ]);

        if ($response->failed()) {
            throw new RuntimeException($this->transport->errorMessage($response));
        }

        $payload = $response->json();
        $rawItems = data_get($payload, 'items', []);
        $rawItems = is_array($rawItems) ? array_values($rawItems) : [];

        return [
            'items' => array_values(array_filter($rawItems, fn (mixed $item): bool => is_array($item) && isset($item['id']))),
            'next_offset' => $offset + count($rawItems),
            'total' => (int) data_get($payload, 'total', $offset + count($rawItems)),
        ];
    }

    /**
     * Fetch Spotify artist metadata for the supplied IDs.
     *
     * @param  list<string>  $ids  The Spotify artist IDs.
     * @return list<array<string, mixed>> The artist payloads.
     */
    public function artists(array $ids): array
    {
        $artists = [];

        foreach (array_chunk(array_values(array_unique($ids)), 50) as $chunk) {
            $response = $this->transport->get('/artists', ['ids' => implode(',', $chunk)]);

            if ($response->failed()) {
                throw new RuntimeException($this->transport->errorMessage($response));
            }

            $artists = array_merge($artists, array_values(array_filter(
                data_get($response->json(), 'artists', []),
                fn (mixed $artist): bool => is_array($artist) && isset($artist['id']),
            )));
        }

        return $artists;
    }

    /**
     * Search Spotify for a likely album edition match.
     *
     * @param  string  $artistName  The canonical artist name.
     * @param  string  $albumTitle  The canonical album title.
     * @return list<array<string, mixed>> The matching album payloads.
     */
    public function searchAlbums(string $artistName, string $albumTitle): array
    {
        $response = $this->transport->get('/search', [
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

    /**
     * Fetch every track from a Spotify album.
     *
     * @param  string  $albumId  The Spotify album ID.
     * @return list<array<string, mixed>> The album track payloads.
     */
    public function albumTracks(string $albumId): array
    {
        return $this->paginate(
            '/albums/'.$albumId.'/tracks',
            null,
            fn (array $track): bool => isset($track['id']),
        );
    }

    /**
     * Fetch every item from a Spotify playlist.
     *
     * @param  string  $playlistId  The Spotify playlist ID.
     * @return list<array<string, mixed>> The playlist item payloads.
     */
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

    /**
     * Fetch the metadata and snapshot used to audit a playlist.
     *
     * @param  string  $playlistId  The Spotify playlist ID.
     * @return array{id: string, name: string, snapshot_id: string|null, public: bool|null, external_urls: array<string, mixed>} The playlist metadata.
     */
    public function playlistDetails(string $playlistId): array
    {
        $response = $this->transport->get('/playlists/'.$playlistId, []);

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

    /**
     * Rename an existing Spotify playlist.
     *
     * @param  string  $playlistId  The Spotify playlist ID.
     * @param  string  $name  The new playlist name.
     */
    public function updatePlaylistName(string $playlistId, string $name): void
    {
        $response = $this->transport->put('/playlists/'.$playlistId, ['name' => $name]);

        if ($response->failed()) {
            throw new RuntimeException('Spotify playlist name could not be updated (HTTP '.$response->status().'). Reconnect Spotify if access was denied.');
        }
    }

    /**
     * Remove managed track URIs from a Spotify playlist.
     *
     * @param  string  $playlistId  The Spotify playlist ID.
     * @param  list<string>  $uris  The track URIs to remove.
     * @param  string|null  $snapshotId  The current Spotify snapshot.
     * @return string|null The new snapshot ID, when Spotify returns one.
     */
    public function removePlaylistItems(string $playlistId, array $uris, ?string $snapshotId): ?string
    {
        foreach (array_chunk($uris, 100) as $chunk) {
            $payload = ['items' => array_map(fn (string $uri): array => ['uri' => $uri], $chunk)];
            if ($snapshotId !== null) {
                $payload['snapshot_id'] = $snapshotId;
            }

            $response = $this->transport->delete('/playlists/'.$playlistId.'/items', $payload);

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
    /**
     * Create a private Spotify playlist for an approved Effemeral draft.
     *
     * @param  string  $name  The playlist name.
     * @param  string  $description  The playlist description.
     * @return array<string, mixed> The created playlist payload.
     */
    public function createPrivatePlaylist(string $name, string $description): array
    {
        $response = $this->transport->post('/me/playlists', [
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

    /**
     * Append track URIs to a Spotify playlist in API-sized batches.
     *
     * @param  string  $playlistId  The Spotify playlist ID.
     * @param  list<string>  $uris  The track URIs to append.
     */
    public function addPlaylistItems(string $playlistId, array $uris): void
    {
        foreach (array_chunk($uris, 100) as $chunk) {
            $response = $this->transport->post('/playlists/'.$playlistId.'/items', ['uris' => $chunk]);

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

            $response = $this->transport->get($path, [
                'limit' => $pageLimit,
                'offset' => $offset,
            ]);

            if ($response->failed()) {
                throw new RuntimeException($this->transport->errorMessage($response));
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
}
