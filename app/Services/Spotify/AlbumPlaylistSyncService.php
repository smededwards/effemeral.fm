<?php

namespace App\Services\Spotify;

use App\Models\AlbumPlaylist;
use App\Models\SpotifyPlaylist;
use RuntimeException;

/**
 * Builds reviewable album-level Spotify changes and applies approved syncs.
 */
class AlbumPlaylistSyncService
{
    public function __construct(
        private SpotifyClient $client,
        private AlbumPlaylistTrackResolver $trackResolver,
    ) {}

    /**
     * Compare the local album playlist with Spotify without changing either side.
     *
     * @param  AlbumPlaylist  $albumPlaylist  The local playlist to compare.
     * @param  string  $destination  The requested Spotify destination mode.
     * @param  SpotifyPlaylist|null  $selectedPlaylist  The existing destination, if selected.
     * @return array<string, mixed> The reviewable sync preview.
     */
    public function preview(AlbumPlaylist $albumPlaylist, string $destination, ?SpotifyPlaylist $selectedPlaylist = null): array
    {
        $targetPlaylistId = match ($destination) {
            'existing' => $selectedPlaylist?->spotify_playlist_id,
            'linked' => $albumPlaylist->spotify_playlist_id,
            default => null,
        };

        if ($destination === 'existing' && $targetPlaylistId === null) {
            throw new RuntimeException('Choose an imported Spotify playlist before reviewing the sync.');
        }

        if ($destination === 'linked' && $targetPlaylistId === null) {
            throw new RuntimeException('This Effemeral playlist is not linked to Spotify.');
        }

        if ($targetPlaylistId !== null && AlbumPlaylist::query()
            ->where('spotify_playlist_id', $targetPlaylistId)
            ->whereKeyNot($albumPlaylist->id)
            ->exists()) {
            throw new RuntimeException('That Spotify playlist is already linked to another Effemeral playlist.');
        }

        $remotePlaylist = $targetPlaylistId === null ? null : $this->client->playlistDetails($targetPlaylistId);
        $currentItems = $remotePlaylist === null ? [] : $this->client->playlistItems($targetPlaylistId);
        $currentUris = $remotePlaylist === null
            ? []
            : collect($currentItems)
                ->map(fn (array $item): mixed => data_get($item, 'track.uri'))
                ->filter(fn (mixed $uri): bool => is_string($uri))
                ->values()
                ->all();
        $managedUris = $albumPlaylist->spotify_pending_track_uris
            ?? $albumPlaylist->spotify_managed_track_uris
            ?? [];
        $occurrences = array_count_values($currentUris);

        foreach (array_unique($managedUris) as $uri) {
            if (($occurrences[$uri] ?? 0) > 1) {
                throw new RuntimeException('A Spotify playlist contains duplicate tracks that Effemeral manages. Remove the duplicate in Spotify, then review the sync again.');
            }
        }

        $managedCurrentUris = array_values(array_filter(
            $currentUris,
            fn (string $uri): bool => in_array($uri, $managedUris, true),
        ));
        $unmanagedUris = array_values(array_filter(
            $currentUris,
            fn (string $uri): bool => ! in_array($uri, $managedUris, true),
        ));
        $desired = $this->trackResolver->resolve($albumPlaylist);
        // Never remove a managed track merely because the local album order changed.
        // A failed add after a destructive remove would otherwise make a reorder
        // appear as lost tracks in Spotify.
        $removeUris = array_values(array_diff($managedCurrentUris, $desired['uris']));
        $addUris = array_values(array_diff($desired['uris'], $currentUris));
        $removeUriSet = array_flip($removeUris);
        $addUriSet = array_flip($addUris);

        if ($desired['uris'] === []) {
            throw new RuntimeException('No Spotify-linked tracks are available. Add albums with matched Spotify tracks before syncing.');
        }

        $incompleteAlbums = array_values(array_filter(
            $desired['albums'],
            fn (array $album): bool => $album['available_tracks'] < $album['total_tracks'],
        ));

        return [
            'destination' => $destination,
            'target_playlist_id' => $targetPlaylistId,
            'destination_name' => $remotePlaylist['name'] ?? 'New private Spotify playlist',
            'remote_name' => $remotePlaylist['name'] ?? null,
            'spotify_url' => data_get($remotePlaylist, 'external_urls.spotify'),
            'snapshot_id' => $remotePlaylist['snapshot_id'] ?? null,
            'current_uris' => $currentUris,
            'remove_uris' => $removeUris,
            'add_uris' => $addUris,
            'remove_tracks' => collect($currentItems)
                ->map(fn (array $item): array => $item['track'] ?? [])
                ->filter(fn (array $track): bool => isset($removeUriSet[$track['uri'] ?? '']))
                ->map(fn (array $track): array => [
                    'title' => $track['name'] ?? 'Untitled track',
                    'album' => data_get($track, 'album.name', 'Unknown album'),
                    'uri' => $track['uri'],
                ])
                ->values()
                ->all(),
            'add_tracks' => array_values(array_filter(
                $desired['tracks'],
                fn (array $track): bool => isset($addUriSet[$track['uri']]),
            )),
            'desired_uris' => $desired['uris'],
            'managed_uris' => array_values(array_diff($desired['uris'], $unmanagedUris)),
            'unmanaged_count' => count($unmanagedUris),
            'albums' => $desired['albums'],
            'incomplete_albums' => $incompleteAlbums,
            'sync_allowed' => $incompleteAlbums === [],
            'local_hash' => $this->playlistHash(
                $albumPlaylist,
                $destination,
                $targetPlaylistId,
                $desired['uris'],
            ),
        ];
    }

    /**
     * Apply a previously reviewed and still-current preview to Spotify.
     *
     * @param  AlbumPlaylist  $albumPlaylist  The local playlist to sync.
     * @param  array<string, mixed>  $preview  The previously reviewed preview.
     */
    public function sync(AlbumPlaylist $albumPlaylist, array $preview): void
    {
        if (($preview['sync_allowed'] ?? true) !== true) {
            throw new RuntimeException('Complete every album before syncing this playlist to Spotify.');
        }

        $destination = (string) ($preview['destination'] ?? '');
        $targetPlaylistId = $preview['target_playlist_id'] ?? null;
        $desired = $this->trackResolver->resolve($albumPlaylist);

        if ($this->playlistHash(
            $albumPlaylist,
            $destination,
            is_string($targetPlaylistId) ? $targetPlaylistId : null,
            $desired['uris'],
        ) !== ($preview['local_hash'] ?? null)) {
            throw new RuntimeException('This Effemeral playlist changed after the preview. Review the Spotify changes again before syncing.');
        }

        $claimedSync = false;

        try {
            if ($destination === 'new') {
                if ($albumPlaylist->spotify_playlist_id !== null) {
                    throw new RuntimeException('This Effemeral playlist is already linked. Review the current Spotify destination before syncing.');
                }

                $this->claimSync($albumPlaylist, $preview['desired_uris']);
                $claimedSync = true;
                $this->assertPreviewStillCurrent($albumPlaylist, $preview, 'new', null);
                $created = $this->client->createPrivatePlaylist(
                    $albumPlaylist->name,
                    'Album playlist managed by Effemeral.',
                );
                $targetPlaylistId = $created['id'];
                $spotifyUrl = $created['external_urls']['spotify'] ?? null;
                $albumPlaylist->update([
                    'spotify_playlist_id' => $targetPlaylistId,
                    'spotify_playlist_url' => $spotifyUrl,
                ]);
                $snapshotId = null;
            } else {
                if (! is_string($targetPlaylistId) || $targetPlaylistId === '') {
                    throw new RuntimeException('The selected Spotify playlist is no longer available. Review the sync again.');
                }

                if ($albumPlaylist->spotify_playlist_id !== null && $albumPlaylist->spotify_playlist_id !== $targetPlaylistId) {
                    throw new RuntimeException('This Effemeral playlist is already linked to another Spotify destination.');
                }

                if (AlbumPlaylist::query()
                    ->where('spotify_playlist_id', $targetPlaylistId)
                    ->whereKeyNot($albumPlaylist->id)
                    ->exists()) {
                    throw new RuntimeException('That Spotify playlist is already linked to another Effemeral playlist.');
                }

                $remotePlaylist = $this->client->playlistDetails($targetPlaylistId);
                if (($remotePlaylist['snapshot_id'] ?? null) !== ($preview['snapshot_id'] ?? null)) {
                    throw new RuntimeException('The Spotify playlist changed after the preview. Review the current changes before syncing.');
                }

                $spotifyUrl = data_get($remotePlaylist, 'external_urls.spotify');
                $snapshotId = $remotePlaylist['snapshot_id'];
                $claimed = $albumPlaylist->newQuery()
                    ->whereKey($albumPlaylist->id)
                    ->where('spotify_sync_status', '<>', 'syncing')
                    ->update([
                        'spotify_playlist_id' => $targetPlaylistId,
                        'spotify_playlist_url' => $spotifyUrl,
                        'spotify_sync_status' => 'syncing',
                        'spotify_sync_error' => null,
                        'spotify_pending_track_uris' => $preview['desired_uris'],
                    ]);

                if ($claimed !== 1) {
                    throw new RuntimeException('A Spotify sync is already in progress. Wait for it to finish before retrying.');
                }
                $claimedSync = true;
                $albumPlaylist->refresh();
                $this->assertPreviewStillCurrent($albumPlaylist, $preview, $destination, $targetPlaylistId);
            }

            if ($destination !== 'new' && $preview['remove_uris'] !== []) {
                $snapshotId = $this->client->removePlaylistItems($targetPlaylistId, $preview['remove_uris'], $snapshotId);
            }

            if ($preview['add_uris'] !== []) {
                $this->client->addPlaylistItems($targetPlaylistId, $preview['add_uris']);
            }

            if ($destination !== 'new' && $preview['remote_name'] !== $albumPlaylist->name) {
                $this->client->updatePlaylistName($targetPlaylistId, $albumPlaylist->name);
            }

            $albumPlaylist->update([
                'spotify_managed_track_uris' => $preview['managed_uris'],
                'spotify_pending_track_uris' => null,
                'spotify_sync_status' => 'synced',
                'spotify_sync_error' => null,
                'spotify_synced_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            if ($claimedSync) {
                $albumPlaylist->refresh();
                if ($albumPlaylist->spotify_sync_status === 'syncing') {
                    $albumPlaylist->update([
                        'spotify_sync_status' => 'failed',
                        'spotify_sync_error' => $exception->getMessage(),
                    ]);
                }
            }

            throw $exception;
        }
    }

    /**
     * @param  list<string>  $desiredUris  The desired managed track URIs.
     */
    private function claimSync(AlbumPlaylist $albumPlaylist, array $desiredUris): void
    {
        $claimed = $albumPlaylist->newQuery()
            ->whereKey($albumPlaylist->id)
            ->where('spotify_sync_status', '<>', 'syncing')
            ->update([
                'spotify_sync_status' => 'syncing',
                'spotify_sync_error' => null,
                'spotify_pending_track_uris' => $desiredUris,
            ]);

        if ($claimed !== 1) {
            throw new RuntimeException('A Spotify sync is already in progress. Wait for it to finish before retrying.');
        }

        $albumPlaylist->refresh();
    }

    /**
     * @param  array<string, mixed>  $preview  The previously generated preview.
     */
    private function assertPreviewStillCurrent(AlbumPlaylist $albumPlaylist, array $preview, string $destination, ?string $targetPlaylistId): void
    {
        $desired = $this->trackResolver->resolve($albumPlaylist);

        if ($this->playlistHash($albumPlaylist, $destination, $targetPlaylistId, $desired['uris']) !== ($preview['local_hash'] ?? null)) {
            throw new RuntimeException('This Effemeral playlist changed after the preview. Review the Spotify changes again before syncing.');
        }
    }

    /**
     * @param  list<string>  $uris  The track URIs used to calculate the hash.
     */
    private function playlistHash(AlbumPlaylist $albumPlaylist, string $destination, ?string $targetPlaylistId, array $uris): string
    {
        return hash('sha256', json_encode([
            $albumPlaylist->id,
            $albumPlaylist->name,
            $destination,
            $targetPlaylistId,
            $uris,
        ], JSON_THROW_ON_ERROR));
    }
}
