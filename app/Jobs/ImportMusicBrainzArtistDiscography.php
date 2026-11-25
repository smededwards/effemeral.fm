<?php

namespace App\Jobs;

use App\Models\Album;
use App\Models\Artist;
use App\Models\MusicBrainzDiscographyImportRun;
use App\Services\MusicBrainz\MusicBrainzClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Enriches existing catalogue albums with matching MusicBrainz release metadata.
 */
class ImportMusicBrainzArtistDiscography implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    /**
     * @param  int  $importRunId  The persisted enrichment run to update.
     */
    public function __construct(public int $importRunId) {}

    /**
     * Match provider release groups to existing albums without importing releases or tracks.
     *
     * @param  MusicBrainzClient  $musicBrainz  The MusicBrainz API client.
     */
    public function handle(MusicBrainzClient $musicBrainz): void
    {
        $run = MusicBrainzDiscographyImportRun::query()->findOrFail($this->importRunId);
        $artist = Artist::query()->findOrFail($run->artist_id);
        $run->update(['status' => 'running', 'started_at' => now(), 'error' => null]);

        try {
            $releaseGroups = $musicBrainz->artistReleaseGroups($artist);
            $run->update(['fetched_count' => count($releaseGroups)]);
            $matched = 0;
            $unmatched = 0;

            foreach ($releaseGroups as $releaseGroup) {
                $releaseGroupId = $releaseGroup['id'] ?? null;
                $title = trim((string) ($releaseGroup['title'] ?? ''));

                if (! is_string($releaseGroupId) || $releaseGroupId === '' || $title === '') {
                    continue;
                }

                $album = Album::query()->withSourceEvidence()->where('musicbrainz_release_group_mbid', $releaseGroupId)->first()
                    ?? Album::query()
                        ->withSourceEvidence()
                        ->where('normalised_title', $this->normalise($title))
                        ->whereHas('artists', fn ($query) => $query->whereKey($artist->id))
                        ->first();

                if ($album === null) {
                    $unmatched++;
                    $run->update([
                        'matched_count' => $matched,
                        'unmatched_count' => $unmatched,
                    ]);

                    continue;
                }

                $album->musicbrainz_release_group_mbid = $releaseGroupId;
                $album->album_type = mb_strtolower((string) ($releaseGroup['primary-type'] ?? 'album'));
                $album->release_date ??= $releaseGroup['first-release-date'] ?? null;
                $album->release_date_precision ??= isset($releaseGroup['first-release-date'])
                    ? $this->datePrecision((string) $releaseGroup['first-release-date'])
                    : null;
                $album->save();
                $album->artists()->syncWithoutDetaching([$artist->id]);

                $matched++;
                $run->update([
                    'matched_count' => $matched,
                    'unmatched_count' => $unmatched,
                ]);
            }

            $run->update(['status' => 'completed', 'completed_at' => now()]);
        } catch (Throwable $exception) {
            $run->update(['status' => 'failed', 'error' => $exception->getMessage(), 'completed_at' => now()]);

            throw $exception;
        }
    }

    private function normalise(string $value): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim($value))) ?? '';
    }

    private function datePrecision(string $date): string
    {
        return match (strlen($date)) {
            4 => 'year',
            7 => 'month',
            default => 'day',
        };
    }
}
