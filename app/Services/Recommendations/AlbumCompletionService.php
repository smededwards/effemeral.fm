<?php

namespace App\Services\Recommendations;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Scrobble;
use App\Models\SpotifySavedAlbum;
use App\Models\Track;
use Illuminate\Support\Collection;

/**
 * Calculates album listening completion for recommendations and review pages.
 */
class AlbumCompletionService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function incompleteAlbums(): Collection
    {
        $legacyAlbums = SpotifySavedAlbum::query()->get()->keyBy('spotify_album_id');
        $heardTrackIds = Scrobble::query()->whereNotNull('track_id')->pluck('track_id')->flip();
        $canonicalAlbums = Album::query()
            ->with(['artists', 'tracks', 'spotifyCollections'])
            ->whereHas('spotifyCollections', fn ($query) => $query->where('collection_type', 'saved'))
            ->get();

        if ($canonicalAlbums->isNotEmpty()) {
            /** @var Collection<int, array<string, mixed>> $incompleteAlbums */
            $incompleteAlbums = $canonicalAlbums
                ->map(function (Album $album) use ($heardTrackIds, $legacyAlbums): ?array {
                    /** @var \Illuminate\Database\Eloquent\Collection<int, Track> $tracks */
                    $tracks = $album->getRelation('tracks');
                    /** @var \Illuminate\Database\Eloquent\Collection<int, Artist> $artists */
                    $artists = $album->getRelation('artists');
                    $legacyAlbum = $legacyAlbums->get($album->spotify_id);
                    $manualOverride = $legacyAlbum?->completion_override;

                    if ($manualOverride === true || $tracks->isEmpty() || ($album->total_tracks !== null && $tracks->count() !== $album->total_tracks)) {
                        return null;
                    }

                    $heard = $tracks->filter(fn (Track $track): bool => $heardTrackIds->has($track->id))->count();
                    if ($manualOverride !== false && $heard === $tracks->count()) {
                        return null;
                    }

                    return [
                        'id' => $legacyAlbum?->id,
                        'canonical_id' => $album->id,
                        'artist' => $artists->sortBy('pivot.artist_position')->first()->name ?? 'Unknown artist',
                        'name' => $album->title,
                        'heard' => $heard,
                        'total' => $tracks->count(),
                        'percentage' => (int) floor(($heard / $tracks->count()) * 100),
                        'spotify_url' => $album->spotify_url,
                        'cover_image_url' => $album->cover_image_url,
                        'manual_override' => $manualOverride !== null,
                    ];
                })
                ->filter(fn (?array $album): bool => $album !== null && $album['id'] !== null)
                ->sortBy('percentage')
                ->take(10)
                ->values();

            return $incompleteAlbums;
        }

        $heardTracks = [];
        foreach (Scrobble::query()->whereNotNull('album_name')->get(['artist_name', 'album_name', 'track_name']) as $scrobble) {
            $albumKey = $this->normalise($scrobble->artist_name).'|'.$this->normalise((string) $scrobble->album_name);
            $heardTracks[$albumKey][$this->normalise($scrobble->track_name)] = true;
        }

        /** @var Collection<int, array<string, mixed>> $incompleteAlbums */
        $incompleteAlbums = SpotifySavedAlbum::query()
            ->latest('saved_at')
            ->get()
            ->map(function (SpotifySavedAlbum $album) use ($heardTracks): ?array {
                $tracks = collect(data_get($album->tracks, 'items', []))
                    ->filter(fn (mixed $track): bool => is_array($track) && is_string($track['name'] ?? null))
                    ->values();

                // Albums with partial tracklists are deferred until Spotify track pagination exists.
                if ($album->completion_override === true || $tracks->isEmpty() || $tracks->count() !== $album->total_tracks) {
                    return null;
                }

                $albumKey = $this->normalise((string) $album->primary_artist_name).'|'.$this->normalise($album->name);
                $heard = $tracks->filter(fn (array $track): bool => isset($heardTracks[$albumKey][$this->normalise($track['name'])]))->count();

                if ($album->completion_override !== false && $heard === $tracks->count()) {
                    return null;
                }

                return [
                    'id' => $album->id,
                    'canonical_id' => null,
                    'artist' => $album->primary_artist_name ?? 'Unknown artist',
                    'name' => $album->name,
                    'heard' => $heard,
                    'total' => $tracks->count(),
                    'percentage' => (int) floor(($heard / $tracks->count()) * 100),
                    'spotify_url' => $album->spotify_url,
                    'cover_image_url' => $album->cover_image_url,
                    'manual_override' => $album->completion_override !== null,
                ];
            })
            ->filter()
            ->sortBy('percentage')
            ->take(10)
            ->values();

        return $incompleteAlbums;
    }

    /**
     * @return array{canonical_id: int|null, heard: int, total: int, percentage: int}
     */
    public function progress(SpotifySavedAlbum $savedAlbum): array
    {
        $canonicalAlbum = Album::query()->with('tracks')->where('spotify_id', $savedAlbum->spotify_album_id)->first();

        if ($canonicalAlbum !== null && $canonicalAlbum->tracks->isNotEmpty()) {
            $heardTrackIds = Scrobble::query()->whereNotNull('track_id')->pluck('track_id')->flip();
            /** @var \Illuminate\Database\Eloquent\Collection<int, Track> $tracks */
            $tracks = $canonicalAlbum->getRelation('tracks');
            $total = $tracks->count();
            $heard = $tracks->filter(fn (Track $track): bool => $heardTrackIds->has($track->id))->count();

            return [
                'canonical_id' => $canonicalAlbum->id,
                'heard' => $heard,
                'total' => $total,
                'percentage' => (int) floor(($heard / $total) * 100),
            ];
        }

        $tracks = collect(data_get($savedAlbum->tracks, 'items', []))
            ->filter(fn (mixed $track): bool => is_array($track) && is_string($track['name'] ?? null))
            ->values();
        $heardTracks = Scrobble::query()
            ->where('artist_name', $savedAlbum->primary_artist_name)
            ->where('album_name', $savedAlbum->name)
            ->pluck('track_name')
            ->map(fn (string $track): string => $this->normalise($track))
            ->flip();
        $total = $tracks->count();
        $heard = $tracks->filter(fn (array $track): bool => $heardTracks->has($this->normalise($track['name'])))->count();

        return [
            'canonical_id' => null,
            'heard' => $heard,
            'total' => $total,
            'percentage' => $total > 0 ? (int) floor(($heard / $total) * 100) : 0,
        ];
    }

    private function normalise(string $value): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim($value))) ?? '';
    }
}
