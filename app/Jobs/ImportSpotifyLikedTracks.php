<?php

namespace App\Jobs;

use App\Models\Artist;
use App\Models\SpotifyImportRun;
use App\Models\SpotifyLikedTrack;
use App\Models\Track;
use App\Services\Catalog\CatalogWriter;
use App\Services\Catalog\GenreWriter;
use App\Services\Spotify\SpotifyClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Imports Spotify liked tracks into the canonical catalogue.
 */
class ImportSpotifyLikedTracks implements ShouldQueue
{
    use Queueable;

    /**
     * @param  int  $importRunId  The persisted import run to update.
     */
    public function __construct(public int $importRunId) {}

    /**
     * Import liked tracks idempotently and update the run counters.
     *
     * @param  SpotifyClient  $client  The Spotify API client.
     * @param  CatalogWriter|null  $catalog  The canonical catalogue writer.
     * @param  GenreWriter|null  $genreWriter  The genre persistence service.
     */
    public function handle(SpotifyClient $client, ?CatalogWriter $catalog = null, ?GenreWriter $genreWriter = null): void
    {
        $catalog ??= app(CatalogWriter::class);
        $genreWriter ??= app(GenreWriter::class);
        $run = SpotifyImportRun::query()->findOrFail($this->importRunId);
        $run->update(['status' => 'running', 'started_at' => now(), 'error' => null]);

        try {
            $items = $client->likedTracks($run->requested_limit === null ? null : (int) $run->requested_limit);
            $imported = 0;
            $updated = 0;
            $skipped = 0;
            $artistIds = [];

            foreach ($items as $item) {
                $track = $item['track'] ?? null;
                if (! is_array($track) || ! is_array($track['album'] ?? null)) {
                    $skipped++;

                    continue;
                }

                $album = $catalog->spotifyAlbum($track['album'], null, 'liked');
                $catalog->spotifyAlbumTracks($album, [$track]);
                foreach ($track['artists'] ?? [] as $artist) {
                    if (is_array($artist) && is_string($artist['id'] ?? null) && $artist['id'] !== '') {
                        $artistIds[$artist['id']] = true;
                    }
                }

                /**
                 * @var Track|null $canonicalTrack
                 */
                $canonicalTrack = $album->tracks()->where('spotify_id', $track['id'])->first();
                $likedTrack = SpotifyLikedTrack::query()->firstOrNew(['spotify_track_id' => $track['id']]);
                $wasExisting = $likedTrack->exists;
                $likedTrack->fill([
                    'spotify_uri' => $track['uri'],
                    'track_name' => $track['name'],
                    'album_id' => $album->id,
                    'track_id' => $canonicalTrack?->id,
                    'saved_at' => $item['added_at'] ?? null,
                    'last_synced_at' => now(),
                ]);
                $likedTrack->save();
                $wasExisting ? $updated++ : $imported++;
            }

            foreach ($client->artists(array_keys($artistIds)) as $artistData) {
                $artist = Artist::query()->where('spotify_id', $artistData['id'])->first();
                if ($artist !== null) {
                    $genreWriter->syncSpotify($artist, $artistData['genres'] ?? []);
                }
            }

            $run->update([
                'status' => 'completed',
                'fetched_count' => count($items),
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
