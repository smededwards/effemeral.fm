<?php

namespace App\Jobs;

use App\Models\LastFmImportRun;
use App\Models\Scrobble;
use App\Services\LastFm\LastFmClient;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ImportLastFmScrobbles implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $importRunId) {}

    public function handle(LastFmClient $client): void
    {
        $run = LastFmImportRun::query()->findOrFail($this->importRunId);
        $run->update(['status' => 'running', 'started_at' => now(), 'error' => null]);

        try {
            $tracks = $client->recentTracks(
                CarbonImmutable::parse((string) $run->getRawOriginal('from_at')),
                CarbonImmutable::parse((string) $run->getRawOriginal('to_at')),
                (int) ($run->requested_limit ?? 100),
            );
            $imported = 0;
            $skipped = 0;

            foreach ($tracks as $track) {
                if ((bool) data_get($track, '@attr.nowplaying', false) || ! data_get($track, 'date.uts')) {
                    $skipped++;

                    continue;
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

                $scrobble->wasRecentlyCreated ? $imported++ : $skipped++;
            }

            $run->update([
                'status' => 'completed',
                'fetched_count' => count($tracks),
                'imported_count' => $imported,
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
