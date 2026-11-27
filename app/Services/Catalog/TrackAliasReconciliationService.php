<?php

namespace App\Services\Catalog;

use App\Models\Album;
use App\Models\Track;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reconciles Last.fm-only track records with matching Spotify tracks.
 */
class TrackAliasReconciliationService
{
    /**
     * Find Last.fm-only tracks that can safely map to Spotify tracks on the same album.
     *
     * @param  int|null  $limit  Maximum number of candidates to inspect.
     * @param  int|null  $trackId  Limit reconciliation to one source track.
     * @return Collection<int, array{source: Track, canonical: Track}>
     */
    public function candidates(?int $limit = null, ?int $trackId = null): Collection
    {
        $query = Track::query()
            ->whereNull('spotify_id')
            ->whereNotNull('lastfm_mbid')
            ->whereHas('album', function ($album): void {
                $album->whereNotNull('spotify_id');
            })
            ->when($trackId !== null, fn ($query) => $query->whereKey($trackId))
            ->with(['album.releaseTracks']);

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query->get()
            ->map(function (Track $source): ?array {
                /** @var Album $album */
                $album = $source->album;
                /** @var EloquentCollection<int, Track> $releaseTracks */
                $releaseTracks = $album->releaseTracks;
                $canonical = $releaseTracks
                    ->first(fn (Track $track): bool => $track->spotify_id !== null
                        && $this->matchKey($track->title) === $this->matchKey($source->title));

                return $canonical instanceof Track
                    ? ['source' => $source, 'canonical' => $canonical]
                    : null;
            })
            ->filter()
            ->values();
    }

    /**
     * Move listening history to the Spotify track and preserve the Last.fm title as an alias.
     *
     * @param  Track  $source  The Last.fm-only track to retire.
     * @param  Track  $canonical  The Spotify-backed track to retain.
     */
    public function reconcile(Track $source, Track $canonical): void
    {
        DB::transaction(function () use ($source, $canonical): void {
            $lastfmMbid = $source->lastfm_mbid;
            $source->update(['lastfm_mbid' => null]);
            $canonical->update([
                'lastfm_mbid' => $canonical->lastfm_mbid ?? $lastfmMbid,
            ]);
            $canonical->aliases()->updateOrCreate(
                [
                    'source' => 'lastfm',
                    'normalised_name' => $this->matchKey($source->title),
                ],
                [
                    'name' => $source->title,
                    'provider_id' => $lastfmMbid,
                ],
            );
            DB::table('scrobbles')
                ->where('track_id', $source->id)
                ->update([
                    'track_id' => $canonical->id,
                    'album_id' => $canonical->album_id,
                ]);
            DB::table('album_track_releases')
                ->where('track_id', $source->id)
                ->delete();

            if (! $this->hasReferences($source)) {
                $source->delete();
            }
        });
    }

    /**
     * Normalize titles for conservative cross-provider matching.
     */
    private function matchKey(string $title): string
    {
        $normalised = mb_strtolower(trim($title));
        $normalised = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalised) ?? $normalised;

        return trim(preg_replace('/\s+/', ' ', $normalised) ?? '');
    }

    /**
     * Keep source tracks that still have references outside their release pivot.
     */
    private function hasReferences(Track $track): bool
    {
        return DB::table('scrobbles')->where('track_id', $track->id)->exists()
            || DB::table('spotify_playlist_items')->where('track_id', $track->id)->exists()
            || DB::table('spotify_liked_tracks')->where('track_id', $track->id)->exists()
            || DB::table('catalog_identity_links')
                ->where('entity_type', 'track')
                ->where('entity_id', $track->id)
                ->exists();
    }
}
