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
                $album->musicbrainz_primary_type = $this->normaliseType($releaseGroup['primary-type'] ?? null);
                $album->setAttribute('musicbrainz_secondary_types', $this->secondaryTypes($releaseGroup));
                $album->canonical_release_type = $this->canonicalReleaseType($releaseGroup);
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

    /**
     * Return a normalized MusicBrainz release-group type.
     *
     * @param  mixed  $type  The raw MusicBrainz primary type.
     * @return string|null The normalized type, when present.
     */
    private function normaliseType(mixed $type): ?string
    {
        $normalised = mb_strtolower(trim((string) $type));

        return $normalised !== '' ? $normalised : null;
    }

    /**
     * Return normalized MusicBrainz secondary release-group types.
     *
     * @param  array<string, mixed>  $releaseGroup  The MusicBrainz release group.
     * @return list<string> The normalized secondary types.
     */
    private function secondaryTypes(array $releaseGroup): array
    {
        return collect($releaseGroup['secondary-types'] ?? [])
            ->filter(fn (mixed $type): bool => is_string($type) && trim($type) !== '')
            ->map(fn (string $type): string => mb_strtolower(trim($type)))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Derive the canonical release classification used by catalogue filters.
     *
     * @param  array<string, mixed>  $releaseGroup  The MusicBrainz release group.
     * @return string The canonical release type.
     */
    private function canonicalReleaseType(array $releaseGroup): string
    {
        $secondaryTypes = $this->secondaryTypes($releaseGroup);
        if (in_array('live', $secondaryTypes, true)) {
            return 'live';
        }
        if (in_array('compilation', $secondaryTypes, true)) {
            return 'compilation';
        }

        return match ($this->normaliseType($releaseGroup['primary-type'] ?? null)) {
            'single' => 'single',
            'ep' => 'ep',
            default => 'album',
        };
    }
}
