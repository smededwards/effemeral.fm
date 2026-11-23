<?php

namespace App\Jobs;

use App\Models\Album;
use App\Models\Artist;
use App\Models\MusicBrainzDiscographyImportRun;
use App\Services\Catalog\CatalogWriter;
use App\Services\MusicBrainz\MusicBrainzClient;
use App\Services\Spotify\SpotifyClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Enriches an existing artist with matching MusicBrainz album metadata.
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
     * Process the artist discography without importing unrelated releases.
     *
     * @param  MusicBrainzClient  $musicBrainz  The MusicBrainz API client.
     * @param  SpotifyClient  $spotify  The Spotify matching client.
     * @param  CatalogWriter  $catalog  The canonical catalogue writer.
     */
    public function handle(MusicBrainzClient $musicBrainz, SpotifyClient $spotify, CatalogWriter $catalog): void
    {
        $run = MusicBrainzDiscographyImportRun::query()->findOrFail($this->importRunId);
        $artist = Artist::query()->findOrFail($run->artist_id);
        $run->update(['status' => 'running', 'started_at' => now(), 'error' => null]);

        try {
            $releaseGroups = $musicBrainz->artistReleaseGroups($artist);
            $run->update(['fetched_count' => count($releaseGroups)]);
            $imported = 0;
            $matched = 0;
            $unmatched = 0;

            foreach ($releaseGroups as $releaseGroup) {
                $releaseGroupId = $releaseGroup['id'] ?? null;
                $title = trim((string) ($releaseGroup['title'] ?? ''));

                if (! is_string($releaseGroupId) || $releaseGroupId === '' || $title === '') {
                    continue;
                }

                $album = Album::query()->where('musicbrainz_release_group_mbid', $releaseGroupId)->first()
                    ?? Album::query()
                        ->where('normalised_title', $this->normalise($title))
                        ->whereHas('artists', fn ($query) => $query->whereKey($artist->id))
                        ->first();
                $wasExisting = $album !== null;
                $spotifyTracksRequested = false;
                $spotifyAlbum = $album?->spotify_id !== null
                    ? null
                    : $this->spotifyMatch($spotify->searchAlbums($artist->name, $title), $artist->name, $title);

                if ($spotifyAlbum !== null) {
                    $spotifyId = $spotifyAlbum['id'] ?? null;
                    $existingSpotifyAlbum = is_string($spotifyId)
                        ? Album::query()->where('spotify_id', $spotifyId)->first()
                        : null;

                    if ($existingSpotifyAlbum !== null && $album !== null && $existingSpotifyAlbum->isNot($album)) {
                        $spotifyAlbum = null;
                    } elseif ($existingSpotifyAlbum !== null) {
                        $album = $existingSpotifyAlbum;
                    } else {
                        $spotifyAlbum['tracks'] = ['items' => $spotify->albumTracks((string) $spotifyId)];
                        $spotifyTracksRequested = true;
                        $album = $catalog->spotifyAlbum($spotifyAlbum, null, null, null, $album);
                    }
                }

                if ($album === null) {
                    $album = $this->createMusicBrainzAlbum($artist, $releaseGroup, $title, $releaseGroupId);
                }

                $spotifyTrackCount = $album->tracks()->whereNotNull('spotify_uri')->count();
                if ($album->spotify_id !== null && ! $spotifyTracksRequested
                    && ($spotifyTrackCount === 0 || ($album->total_tracks !== null && $spotifyTrackCount < $album->total_tracks))) {
                    $catalog->spotifyAlbumTracks($album, $spotify->albumTracks($album->spotify_id));
                }

                $album->musicbrainz_release_group_mbid = $releaseGroupId;
                $album->album_type = mb_strtolower((string) ($releaseGroup['primary-type'] ?? 'album'));
                $album->release_date ??= $releaseGroup['first-release-date'] ?? null;
                $album->release_date_precision ??= isset($releaseGroup['first-release-date'])
                    ? $this->datePrecision((string) $releaseGroup['first-release-date'])
                    : null;
                $album->save();
                $album->artists()->syncWithoutDetaching([$artist->id]);

                if ($album->spotify_id !== null && $album->tracks()->where('spotify_uri', 'like', 'spotify:track:%')->exists()) {
                    $matched++;
                } else {
                    $unmatched++;
                }

                $imported += $wasExisting ? 0 : 1;
                $run->update([
                    'imported_count' => $imported,
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

    /**
     * @param  array<string, mixed>  $releaseGroup  The MusicBrainz release group.
     */
    private function createMusicBrainzAlbum(Artist $artist, array $releaseGroup, string $title, string $releaseGroupId): Album
    {
        $date = $releaseGroup['first-release-date'] ?? null;
        $album = Album::query()->create([
            'title' => $title,
            'normalised_title' => $this->normalise($title),
            'album_type' => mb_strtolower((string) ($releaseGroup['primary-type'] ?? 'album')),
            'musicbrainz_release_group_mbid' => $releaseGroupId,
            'release_date' => is_string($date) ? $date : null,
            'release_date_precision' => is_string($date) ? $this->datePrecision($date) : null,
        ]);
        $album->artists()->attach($artist->id);

        return $album;
    }

    /**
     * @param  list<array<string, mixed>>  $results
     * @return array<string, mixed>|null
     */
    private function spotifyMatch(array $results, string $artistName, string $title): ?array
    {
        $expectedTitle = $this->editionTitle($title);
        $matches = collect($results)
            ->filter(fn (array $album): bool => is_string($album['id'] ?? null) && $album['id'] !== ''
                && $this->editionTitle((string) ($album['name'] ?? '')) === $expectedTitle
                && collect($album['artists'] ?? [])->contains(fn (mixed $artist): bool => is_array($artist)
                    && $this->normalise((string) ($artist['name'] ?? '')) === $this->normalise($artistName)))
            ->unique('id')
            ->sortBy(fn (array $album): int => $this->editionPenalty((string) ($album['name'] ?? '')))
            ->values();

        if ($matches->isEmpty()) {
            return null;
        }

        $bestPenalty = $this->editionPenalty((string) $matches->first()['name']);
        $best = $matches->filter(fn (array $album): bool => $this->editionPenalty((string) ($album['name'] ?? '')) === $bestPenalty);

        return $best->count() === 1 ? $best->first() : null;
    }

    private function normalise(string $value): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim($value))) ?? '';
    }

    private function editionTitle(string $title): string
    {
        $title = preg_replace('/\b(?:\d{4}\s+)?(?:remaster(?:ed)?|deluxe(?: edition)?|expanded(?: edition)?|special edition|bonus tracks?)\b/i', '', $title) ?? $title;
        $title = preg_replace('/\b(?:\d+(?:st|nd|rd|th)\s+)?anniversary(?: edition)?\b/i', '', $title) ?? $title;

        return $this->normalise(preg_replace('/[^\pL\pN]+/u', ' ', $title) ?? $title);
    }

    private function editionPenalty(string $title): int
    {
        return match (true) {
            preg_match('/\banniversary\b/i', $title) === 1 => 3,
            preg_match('/\b(?:deluxe|expanded|special edition|bonus tracks?)\b/i', $title) === 1 => 2,
            preg_match('/\bremaster(?:ed)?\b/i', $title) === 1 => 1,
            default => 0,
        };
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
