<?php

namespace App\Jobs;

use App\Models\Artist;
use App\Models\SpotifyPlaylist;
use App\Models\SpotifyPlaylistImportRun;
use App\Services\Catalog\CatalogWriter;
use App\Services\Catalog\GenreWriter;
use App\Services\Spotify\SpotifyClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ImportSpotifyPlaylists implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $importRunId) {}

    public function handle(SpotifyClient $client, ?CatalogWriter $catalog = null, ?GenreWriter $genreWriter = null): void
    {
        $catalog ??= app(CatalogWriter::class);
        $genreWriter ??= app(GenreWriter::class);
        $run = SpotifyPlaylistImportRun::query()->findOrFail($this->importRunId);
        $run->update(['status' => 'running', 'started_at' => now(), 'error' => null]);

        try {
            $playlists = $client->playlists($run->requested_limit === null ? null : (int) $run->requested_limit);
            $fetched = 0;
            $imported = 0;
            $updated = 0;
            $skipped = 0;
            $artistIds = [];

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
                    'tracks_total' => data_get($playlistData, 'tracks.total', 0),
                    'snapshot_id' => $playlistData['snapshot_id'] ?? null,
                ]);
                $playlist->last_synced_at = now();
                $playlist->save();
                $wasExisting ? $updated++ : $imported++;

                $items = $client->playlistItems($playlistId);
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
                        $playlistId,
                    );
                    $spotifyTrackId = $track['id'] ?? null;
                    $playlist->items()->create([
                        'album_id' => $album->id,
                        'track_id' => $spotifyTrackId
                            ? $album->tracks()->where('spotify_id', $spotifyTrackId)->value('id')
                            : null,
                        'spotify_track_id' => $spotifyTrackId,
                        'spotify_uri' => $track['uri'] ?? null,
                        'track_name' => $track['name'] ?? null,
                        'position' => $position,
                        'added_at' => $item['added_at'] ?? null,
                        'is_local' => $item['is_local'] ?? false,
                    ]);
                    $fetched++;
                }
            }

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
                'completed_at' => now(),
            ]);
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
