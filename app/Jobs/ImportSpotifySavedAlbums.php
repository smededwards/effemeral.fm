<?php

namespace App\Jobs;

use App\Models\SpotifyAlbumImportRun;
use App\Models\SpotifySavedAlbum;
use App\Services\Spotify\SpotifyClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ImportSpotifySavedAlbums implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $importRunId) {}

    public function handle(SpotifyClient $client): void
    {
        $run = SpotifyAlbumImportRun::query()->findOrFail($this->importRunId);
        $run->update(['status' => 'running', 'started_at' => now(), 'error' => null]);

        try {
            $items = $client->savedAlbums($run->requested_limit === null ? null : (int) $run->requested_limit);
            $imported = 0;
            $updated = 0;
            $skipped = 0;

            foreach ($items as $item) {
                $album = $item['album'];
                $albumId = (string) ($album['id'] ?? '');

                if ($albumId === '') {
                    $skipped++;

                    continue;
                }

                $savedAlbum = SpotifySavedAlbum::query()->firstOrNew(['spotify_album_id' => $albumId]);
                $wasExisting = $savedAlbum->exists;
                $savedAlbum->fill([
                    'spotify_uri' => $album['uri'] ?? 'spotify:album:'.$albumId,
                    'spotify_url' => data_get($album, 'external_urls.spotify'),
                    'name' => $album['name'] ?? 'Unknown album',
                    'album_type' => $album['album_type'] ?? 'album',
                    'total_tracks' => $album['total_tracks'] ?? null,
                    'release_date' => $album['release_date'] ?? null,
                    'release_date_precision' => $album['release_date_precision'] ?? null,
                    'primary_artist_id' => data_get($album, 'artists.0.id'),
                    'primary_artist_name' => data_get($album, 'artists.0.name'),
                    'artists' => $album['artists'] ?? [],
                    'tracks' => data_get($album, 'tracks.items', []),
                    'cover_image_url' => data_get($album, 'images.0.url'),
                    'saved_at' => $item['added_at'] ?? null,
                    'last_synced_at' => now(),
                ]);

                if ($savedAlbum->isDirty() || ! $wasExisting) {
                    $savedAlbum->save();
                    $wasExisting ? $updated++ : $imported++;
                } else {
                    $skipped++;
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
