<?php

namespace App\Jobs;

use App\Models\Artist;
use App\Models\SpotifyImportRun;
use App\Models\SpotifySavedAlbum;
use App\Services\Catalog\CatalogWriter;
use App\Services\Catalog\GenreWriter;
use App\Services\Spotify\SpotifyClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Imports Spotify saved albums in resumable pages.
 */
class ImportSpotifySavedAlbums implements ShouldQueue
{
    use Queueable;

    /**
     * @param  int  $importRunId  The persisted import run to update.
     */
    public function __construct(public int $importRunId) {}

    /**
     * Process the next saved-album page and persist its offset.
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
            $imported = (int) $run->imported_count;
            $updated = (int) $run->updated_count;
            $skipped = (int) $run->skipped_count;
            $artistIds = [];

            do {
                $remaining = $run->requested_limit === null
                    ? 50
                    : min(50, (int) $run->requested_limit - $offset);
                if ($remaining < 1) {
                    break;
                }

                $page = $client->savedAlbumsPage($offset, $remaining);
                $items = $page['items'];
                $run->update(['total_count' => $page['total']]);

                foreach ($items as $item) {
                    $album = $item['album'];
                    $albumId = (string) ($album['id'] ?? '');

                    foreach ($album['artists'] ?? [] as $artist) {
                        if (is_array($artist) && is_string($artist['id'] ?? null) && $artist['id'] !== '') {
                            $artistIds[$artist['id']] = true;
                        }
                    }

                    if ($albumId === '') {
                        $skipped++;

                        continue;
                    }

                    $canonicalAlbum = $catalog->spotifyAlbum($album, $item['added_at'] ?? null);
                    $catalog->spotifyAlbumTracks($canonicalAlbum, $client->albumTracks($albumId));

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
                    ]);

                    if ($savedAlbum->isDirty() || ! $wasExisting) {
                        $savedAlbum->last_synced_at = now();
                        $savedAlbum->save();
                        $wasExisting ? $updated++ : $imported++;
                    } else {
                        $skipped++;
                    }
                }

                $offset = $page['next_offset'];
                $run->update([
                    'next_offset' => $offset,
                    'fetched_count' => $offset,
                    'imported_count' => $imported,
                    'updated_count' => $updated,
                    'skipped_count' => $skipped,
                ]);
            } while ($items !== [] && $offset < $page['total']);

            foreach ($client->artists(array_keys($artistIds)) as $artistData) {
                $artist = Artist::query()->where('spotify_id', $artistData['id'])->first();
                if ($artist !== null) {
                    $genreWriter->syncSpotify($artist, $artistData['genres'] ?? []);
                }
            }

            $run->update([
                'status' => 'completed',
                'fetched_count' => $offset,
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
