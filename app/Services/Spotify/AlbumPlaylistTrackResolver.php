<?php

namespace App\Services\Spotify;

use App\Models\Album;
use App\Models\AlbumPlaylist;
use App\Models\Track;
use Illuminate\Support\Collection;

/**
 * Resolves the local album playlist into ordered Spotify track content.
 */
class AlbumPlaylistTrackResolver
{
    /**
     * @return array{uris: list<string>, tracks: list<array{uri: string, title: string, album: string, artist: string, album_id: int}>, albums: list<array<string, mixed>>} The desired Spotify content.
     */
    public function resolve(AlbumPlaylist $albumPlaylist): array
    {
        /** @var Collection<int, Album> $albums */
        $albums = $albumPlaylist->albums()
            ->with([
                'artists',
                'releaseTracks' => fn ($query) => $query
                    ->orderBy('album_track_releases.disc_number')
                    ->orderBy('album_track_releases.track_number')
                    ->orderBy('tracks.id'),
            ])
            ->get();
        $uris = [];
        $tracksByUri = [];
        $albumSummaries = [];

        foreach ($albums as $album) {
            /** @var \Illuminate\Database\Eloquent\Collection<int, Track> $tracks */
            $tracks = $album->releaseTracks;
            $albumTracks = $tracks
                ->map(function (Track $track): ?array {
                    $uri = $track->spotify_uri;

                    if (! is_string($uri) || ! str_starts_with($uri, 'spotify:track:')) {
                        $uri = is_string($track->spotify_id) && $track->spotify_id !== ''
                            ? 'spotify:track:'.$track->spotify_id
                            : null;
                    }

                    return $uri === null ? null : ['uri' => $uri, 'title' => $track->title];
                })
                ->filter(fn (?array $track): bool => $track !== null)
                ->values()
                ->all();
            $albumUris = [];

            foreach ($albumTracks as $albumTrack) {
                $uri = $albumTrack['uri'];
                $albumUris[] = $uri;
                $tracksByUri[$uri] ??= [
                    'uri' => $uri,
                    'title' => $albumTrack['title'],
                    'album' => $album->title,
                    'artist' => $album->artists->pluck('name')->implode(', ') ?: 'Unknown artist',
                    'album_id' => $album->id,
                ];
            }

            $uris = [...$uris, ...$albumUris];
            $expectedTrackCount = max((int) ($album->total_tracks ?? 0), $tracks->count());
            $albumSummaries[] = [
                'album_id' => $album->id,
                'title' => $album->title,
                'artist' => $album->artists->pluck('name')->implode(', ') ?: 'Unknown artist',
                'available_tracks' => count($albumUris),
                'total_tracks' => $expectedTrackCount,
            ];
        }

        return [
            'uris' => array_values(array_unique($uris)),
            'tracks' => array_values($tracksByUri),
            'albums' => $albumSummaries,
        ];
    }
}
