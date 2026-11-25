<?php

namespace App\Services\Recommendations;

use App\Models\Album;
use App\Models\AlbumRecommendation;
use App\Models\SpotifySavedAlbum;
use App\Models\Track;
use Illuminate\Database\Eloquent\Collection;

/**
 * Resolves canonical and imported Spotify track URIs for a recommendation.
 */
class RecommendationTrackResolver
{
    /**
     * @return list<string> The canonical Spotify track URIs.
     */
    public function resolve(AlbumRecommendation $recommendation): array
    {
        /** @var Album|null $album */
        $album = $recommendation->getRelation('album');
        /** @var SpotifySavedAlbum|null $legacyAlbum */
        $legacyAlbum = $recommendation->getRelation('spotifySavedAlbum');

        if ($album !== null && $album->getRelation('releaseTracks')->isNotEmpty()) {
            /** @var Collection<int, Track> $tracks */
            $tracks = $album->getRelation('releaseTracks');

            return $tracks
                ->pluck('spotify_uri')
                ->filter(fn (mixed $uri): bool => is_string($uri) && str_starts_with($uri, 'spotify:track:'))
                ->values()
                ->all();
        }

        if ($legacyAlbum === null) {
            return [];
        }

        return collect(data_get($legacyAlbum->tracks, 'items', []))
            ->map(fn (mixed $track): mixed => is_array($track) ? ($track['uri'] ?? null) : null)
            ->filter(fn (mixed $uri): bool => is_string($uri) && str_starts_with($uri, 'spotify:track:'))
            ->values()
            ->all();
    }
}
