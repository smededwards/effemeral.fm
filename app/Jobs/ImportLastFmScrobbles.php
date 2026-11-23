<?php

namespace App\Jobs;

use App\Models\Album;
use App\Models\Artist;
use App\Models\LastFmImportRun;
use App\Models\Scrobble;
use App\Services\Catalog\CatalogWriter;
use App\Services\Catalog\GenreWriter;
use App\Services\LastFm\LastFmClient;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Imports Last.fm scrobbles while preserving progress and source identity.
 */
class ImportLastFmScrobbles implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    /**
     * @param  int  $importRunId  The persisted import run to process.
     */
    public function __construct(public int $importRunId) {}

    /**
     * Import the requested range and continue past individual track failures.
     *
     * @param  LastFmClient  $client  The Last.fm API client.
     * @param  CatalogWriter|null  $catalog  The canonical catalogue writer.
     * @param  GenreWriter|null  $genreWriter  The genre persistence service.
     */
    public function handle(LastFmClient $client, ?CatalogWriter $catalog = null, ?GenreWriter $genreWriter = null): void
    {
        $catalog ??= app(CatalogWriter::class);
        $genreWriter ??= app(GenreWriter::class);
        $run = LastFmImportRun::query()->findOrFail($this->importRunId);
        $run->update(['status' => 'running', 'started_at' => now(), 'error' => null]);

        try {
            $from = CarbonImmutable::parse((string) $run->getRawOriginal('from_at'));
            $to = CarbonImmutable::parse((string) $run->getRawOriginal('to_at'));
            $imported = 0;
            $skipped = 0;
            $failed = 0;
            $artistIds = [];
            $errors = [];

            $client->eachRecentTrack(
                $from,
                $to,
                $run->requested_limit === null ? null : (int) $run->requested_limit,
                function (array $track) use (&$imported, &$skipped, &$failed, &$artistIds, &$errors, $catalog): void {
                    try {
                        if ((bool) data_get($track, '@attr.nowplaying', false) || ! data_get($track, 'date.uts')) {
                            $skipped++;

                            return;
                        }

                        $sourceKey = hash('sha256', implode('|', [
                            data_get($track, 'url', ''),
                            data_get($track, 'date.uts', ''),
                            data_get($track, 'artist.#text', ''),
                            data_get($track, 'name', ''),
                        ]));

                        $scrobble = Scrobble::query()->firstOrCreate(
                            ['source_key' => $sourceKey],
                            [
                                'source' => 'lastfm',
                                'artist_name' => data_get($track, 'artist.#text', 'Unknown artist'),
                                'artist_mbid' => data_get($track, 'artist.mbid') ?: null,
                                'track_name' => data_get($track, 'name', 'Unknown track'),
                                'track_mbid' => data_get($track, 'mbid') ?: null,
                                'album_name' => data_get($track, 'album.#text') ?: null,
                                'album_mbid' => data_get($track, 'album.mbid') ?: null,
                                'source_url' => data_get($track, 'url') ?: null,
                                'listened_at' => CarbonImmutable::createFromTimestampUTC((int) data_get($track, 'date.uts')),
                            ],
                        );

                        $catalogTrack = $catalog->lastFmScrobble([
                            'artist_name' => $scrobble->artist_name,
                            'artist_mbid' => $scrobble->artist_mbid,
                            'album_name' => $scrobble->album_name,
                            'album_mbid' => $scrobble->album_mbid,
                            'track_name' => $scrobble->track_name,
                            'track_mbid' => $scrobble->track_mbid,
                        ]);
                        $album = $catalogTrack === null ? null : Album::query()->with('artists')->find($catalogTrack->album_id);
                        $artistId = $album?->artists()->value('artists.id');
                        if ($artistId !== null) {
                            $artistIds[(int) $artistId] = true;
                        }
                        $scrobble->update([
                            'artist_id' => $artistId === null ? null : (int) $artistId,
                            'album_id' => $catalogTrack?->album_id,
                            'track_id' => $catalogTrack?->id,
                        ]);

                        $scrobble->wasRecentlyCreated ? $imported++ : $skipped++;
                    } catch (Throwable $exception) {
                        $failed++;
                        $errors[] = 'Track import failed: '.$exception->getMessage();
                    }
                },
                function (int $fetched) use ($run, &$imported, &$skipped, &$failed): void {
                    $run->update([
                        'fetched_count' => $fetched,
                        'imported_count' => $imported,
                        'skipped_count' => $skipped,
                        'failed_count' => $failed,
                    ]);
                },
            );

            foreach (Artist::query()->whereIn('id', array_keys($artistIds))->get() as $artist) {
                try {
                    $genreWriter->syncLastFm($artist, $client->artistTags($artist->name, $artist->lastfm_mbid));
                } catch (Throwable $exception) {
                    $failed++;
                    $errors[] = 'Genre import failed for '.$artist->name.': '.$exception->getMessage();
                }
            }

            $run->update([
                'status' => 'completed',
                'fetched_count' => $run->fresh()->fetched_count,
                'imported_count' => $imported,
                'skipped_count' => $skipped,
                'failed_count' => $failed,
                'error' => $errors === [] ? null : implode("\n", array_slice($errors, 0, 20)),
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
