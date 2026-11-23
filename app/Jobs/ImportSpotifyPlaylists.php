<?php

namespace App\Jobs;

use App\Models\Album;
use App\Models\Artist;
use App\Models\SpotifyPlaylist;
use App\Models\SpotifyPlaylistImportRun;
use App\Services\Catalog\CatalogWriter;
use App\Services\Catalog\GenreWriter;
use App\Services\Spotify\SpotifyClient;
use App\Services\Spotify\SpotifyRateLimitException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportSpotifyPlaylists implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public function __construct(public int $importRunId, public ?string $playlistId = null) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(SpotifyClient $client, ?CatalogWriter $catalog = null, ?GenreWriter $genreWriter = null): void
    {
        $catalog ??= app(CatalogWriter::class);
        $genreWriter ??= app(GenreWriter::class);
        $run = SpotifyPlaylistImportRun::query()->findOrFail($this->importRunId);
        $resume = in_array($run->status, ['failed', 'running'], true) && (int) $run->next_offset > 0;
        if (! $resume) {
            $run->update([
                'next_offset' => 0,
                'total_count' => null,
                'fetched_count' => 0,
                'imported_count' => 0,
                'updated_count' => 0,
                'skipped_count' => 0,
            ]);
        }
        $run->update(['status' => 'running', 'started_at' => now(), 'error' => null]);

        try {
            $offset = (int) $run->next_offset;
            $fetched = (int) $run->fetched_count;
            $imported = (int) $run->imported_count;
            $updated = (int) $run->updated_count;
            $skipped = (int) $run->skipped_count;
            $artistIds = [];
            $trackIdsByAlbum = [];
            $errors = [];

            do {
                if ($this->playlistId !== null) {
                    $playlists = [[
                        'id' => $this->playlistId,
                        'name' => SpotifyPlaylist::query()->where('spotify_playlist_id', $this->playlistId)->value('name') ?? 'Untitled playlist',
                    ]];
                    $page = ['next_offset' => 1, 'total' => 1];
                } else {
                    $remaining = $run->requested_limit === null
                        ? 50
                        : min(50, (int) $run->requested_limit - $offset);
                    if ($remaining < 1) {
                        break;
                    }

                    $page = $client->playlistsPage($offset, $remaining);
                    $playlists = $page['items'];
                    $run->update(['total_count' => $page['total']]);
                }

                foreach ($playlists as $playlistData) {
                    $playlistId = (string) ($playlistData['id'] ?? '');

                    if ($playlistId === '') {
                        $skipped++;

                        continue;
                    }

                    $playlist = SpotifyPlaylist::query()->firstOrNew(['spotify_playlist_id' => $playlistId]);
                    $wasExisting = $playlist->exists;
                    $playlist->fill([
                        'name' => $playlistData['name'] ?? 'Untitled playlist',
                        'description' => $playlistData['description'] ?? null,
                        'spotify_url' => data_get($playlistData, 'external_urls.spotify'),
                        'owner_id' => data_get($playlistData, 'owner.id'),
                        'public' => $playlistData['public'] ?? null,
                        'collaborative' => $playlistData['collaborative'] ?? false,
                        'tracks_total' => data_get($playlistData, 'items.total', data_get($playlistData, 'tracks.total', 0)),
                        'snapshot_id' => $playlistData['snapshot_id'] ?? null,
                        'last_import_attempted_at' => now(),
                    ]);
                    $playlist->save();

                    $snapshotUnchanged = $this->playlistId === null
                        && $wasExisting
                        && $playlist->last_synced_at !== null
                        && is_string($playlistData['snapshot_id'] ?? null)
                        && $playlist->snapshot_id === $playlistData['snapshot_id'];

                    if ($snapshotUnchanged) {
                        $fetched += $playlist->items()->count();
                        $updated++;

                        continue;
                    }

                    try {
                        $items = $client->playlistItems($playlistId);
                        $playlistFetched = 0;

                        DB::transaction(function () use ($catalog, $items, $playlist, &$artistIds, &$playlistFetched, &$skipped, &$trackIdsByAlbum): void {
                            $playlist->items()->delete();

                            foreach ($items as $position => $item) {
                                $track = $item['track'] ?? null;
                                if (! is_array($track) || ! is_array($track['album'] ?? null)) {
                                    $skipped++;

                                    continue;
                                }

                                foreach ($track['album']['artists'] ?? [] as $artist) {
                                    if (is_array($artist) && is_string($artist['id'] ?? null) && $artist['id'] !== '') {
                                        $artistIds[$artist['id']] = true;
                                    }
                                }

                                $album = $catalog->spotifyAlbum(
                                    $track['album'],
                                    null,
                                    'playlist',
                                    $playlist->spotify_playlist_id,
                                );
                                $spotifyTrackId = $track['id'] ?? null;
                                if (! array_key_exists($album->id, $trackIdsByAlbum)) {
                                    $trackIdsByAlbum[$album->id] = $album->tracks()->pluck('id', 'spotify_id')->all();
                                }
                                $playlist->items()->create([
                                    'album_id' => $album->id,
                                    'track_id' => $spotifyTrackId ? ($trackIdsByAlbum[$album->id][$spotifyTrackId] ?? null) : null,
                                    'spotify_track_id' => $spotifyTrackId,
                                    'spotify_uri' => $track['uri'] ?? null,
                                    'track_name' => $track['name'] ?? null,
                                    'position' => $position,
                                    'added_at' => $item['added_at'] ?? null,
                                    'is_local' => $item['is_local'] ?? false,
                                ]);
                                $playlistFetched++;
                            }
                        });

                        $albumIds = collect($items)
                            ->map(fn (array $item): mixed => data_get($item, 'track.album.id'))
                            ->filter(fn (mixed $albumId): bool => is_string($albumId) && $albumId !== '')
                            ->unique()
                            ->values();

                        foreach ($albumIds as $albumId) {
                            $album = Album::query()->where('spotify_id', $albumId)->first();
                            if ($album === null || ($album->total_tracks !== null
                                && $album->tracks()->whereNotNull('spotify_uri')->count() === $album->total_tracks)) {
                                continue;
                            }

                            $catalog->spotifyAlbumTracks($album, $client->albumTracks($albumId));
                        }

                        $playlist->update([
                            'last_synced_at' => now(),
                            'last_import_error' => null,
                        ]);
                        $fetched += $playlistFetched;
                        $wasExisting ? $updated++ : $imported++;
                        $run->update([
                            'fetched_count' => $fetched,
                            'imported_count' => $imported,
                            'updated_count' => $updated,
                            'skipped_count' => $skipped,
                        ]);
                    } catch (SpotifyRateLimitException $exception) {
                        throw $exception;
                    } catch (Throwable $exception) {
                        $playlist->update(['last_import_error' => $exception->getMessage()]);
                        $errors[] = $playlist->name.': '.$exception->getMessage();
                        $run->update([
                            'fetched_count' => $fetched,
                            'imported_count' => $imported,
                            'updated_count' => $updated,
                            'skipped_count' => $skipped,
                            'error' => implode("\n", $errors),
                        ]);
                    }
                }

                $offset = $page['next_offset'];
                $run->update(['next_offset' => $offset]);
            } while ($this->playlistId === null && $playlists !== [] && $offset < $page['total']);

            foreach ($client->artists(array_keys($artistIds)) as $artistData) {
                $artist = Artist::query()->where('spotify_id', $artistData['id'])->first();
                if ($artist !== null) {
                    $genreWriter->syncSpotify($artist, $artistData['genres'] ?? []);
                }
            }

            $run->update([
                'status' => 'completed',
                'fetched_count' => $fetched,
                'imported_count' => $imported,
                'updated_count' => $updated,
                'skipped_count' => $skipped,
                'error' => $errors === [] ? null : implode("\n", $errors),
                'completed_at' => now(),
            ]);
        } catch (SpotifyRateLimitException $exception) {
            $run->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            if ($exception->retryable) {
                throw $exception;
            }
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            throw $exception;
        }
    }
}
