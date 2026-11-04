<?php

namespace App\Services\Catalog;

use App\Models\Album;
use App\Models\Artist;
use App\Models\SpotifyAlbumCollection;
use App\Models\Track;
use Illuminate\Support\Arr;

class CatalogWriter
{
    /** @param array<string, mixed> $albumData */
    public function spotifyAlbum(
        array $albumData,
        mixed $savedAt = null,
        string $collectionType = 'saved',
        ?string $playlistId = null,
    ): Album {
        $title = (string) ($albumData['name'] ?? 'Unknown album');
        $normalisedTitle = $this->normalise($title);
        $primaryArtist = $this->normalise((string) data_get($albumData, 'artists.0.name', ''));
        $album = isset($albumData['id']) && $albumData['id'] !== ''
            ? Album::query()->where('spotify_id', $albumData['id'])->first()
            : null;
        $album ??= Album::query()
            ->where('normalised_title', $normalisedTitle)
            ->whereHas('artists', fn ($query) => $query->where('normalised_name', $primaryArtist))
            ->first();
        $album ??= new Album;
        $album->fill([
            'title' => $title,
            'normalised_title' => $normalisedTitle,
            'album_type' => $albumData['album_type'] ?? null,
            'spotify_id' => $albumData['id'] ?? null,
            'spotify_uri' => $albumData['uri'] ?? null,
            'spotify_url' => data_get($albumData, 'external_urls.spotify'),
            'total_tracks' => $albumData['total_tracks'] ?? null,
            'release_date' => $albumData['release_date'] ?? null,
            'release_date_precision' => $albumData['release_date_precision'] ?? null,
            'cover_image_url' => data_get($albumData, 'images.0.url'),
        ]);
        $album->save();

        foreach (Arr::wrap($albumData['artists'] ?? []) as $position => $artistData) {
            if (! is_array($artistData) || ! is_string($artistData['name'] ?? null)) {
                continue;
            }

            $artist = isset($artistData['id']) && $artistData['id'] !== ''
                ? Artist::query()->where('spotify_id', $artistData['id'])->first()
                : null;
            $artist ??= Artist::query()->firstOrNew(['normalised_name' => $this->normalise($artistData['name'])]);
            $artist->fill([
                'name' => $artistData['name'],
                'normalised_name' => $this->normalise($artistData['name']),
                'spotify_id' => $artistData['id'] ?? null,
            ]);
            $artist->save();
            $album->artists()->syncWithoutDetaching([$artist->id => ['artist_position' => $position]]);
        }

        foreach (Arr::wrap(data_get($albumData, 'tracks.items', [])) as $trackData) {
            if (! is_array($trackData) || ! is_string($trackData['name'] ?? null)) {
                continue;
            }

            $track = isset($trackData['id']) && $trackData['id'] !== ''
                ? Track::query()->where('spotify_id', $trackData['id'])->first()
                : null;
            $track ??= Track::query()->firstOrNew([
                'album_id' => $album->id,
                'normalised_title' => $this->normalise($trackData['name']),
            ]);
            $track->fill([
                'album_id' => $album->id,
                'title' => $trackData['name'],
                'normalised_title' => $this->normalise($trackData['name']),
                'spotify_id' => $trackData['id'] ?? null,
                'spotify_uri' => $trackData['uri'] ?? null,
                'disc_number' => $trackData['disc_number'] ?? null,
                'track_number' => $trackData['track_number'] ?? null,
                'duration_ms' => $trackData['duration_ms'] ?? null,
            ]);
            $track->save();
        }

        SpotifyAlbumCollection::query()->updateOrCreate(
            ['album_id' => $album->id, 'collection_type' => $collectionType, 'spotify_playlist_id' => $playlistId],
            ['saved_at' => $savedAt],
        );

        return $album;
    }

    public function lastFmScrobble(array $data): ?Track
    {
        $artistName = (string) ($data['artist_name'] ?? 'Unknown artist');
        $artist = isset($data['artist_mbid']) && $data['artist_mbid'] !== ''
            ? Artist::query()->where('lastfm_mbid', $data['artist_mbid'])->first()
            : null;
        $artist ??= Artist::query()->firstOrNew(['normalised_name' => $this->normalise($artistName)]);
        $artist->fill([
            'name' => $artistName,
            'normalised_name' => $this->normalise($artistName),
            'lastfm_mbid' => $data['artist_mbid'] ?? null,
        ]);
        $artist->save();

        $albumName = $data['album_name'] ?? null;
        $trackName = (string) ($data['track_name'] ?? 'Unknown track');
        if (! is_string($albumName) || $albumName === '') {
            return null;
        }

        $normalisedAlbumName = $this->normalise($albumName);
        $album = isset($data['album_mbid']) && $data['album_mbid'] !== ''
            ? Album::query()->where('lastfm_mbid', $data['album_mbid'])->first()
            : null;
        $album ??= Album::query()
            ->where('normalised_title', $normalisedAlbumName)
            ->whereHas('artists', fn ($query) => $query->where('normalised_name', $artist->normalised_name))
            ->first();
        $album ??= new Album;
        $album->fill([
            'title' => $albumName,
            'normalised_title' => $normalisedAlbumName,
            'lastfm_mbid' => $data['album_mbid'] ?? null,
        ]);
        $album->save();
        $album->artists()->syncWithoutDetaching([$artist->id => ['artist_position' => 0]]);

        $track = isset($data['track_mbid']) && $data['track_mbid'] !== ''
            ? Track::query()->where('lastfm_mbid', $data['track_mbid'])->first()
            : null;
        $track ??= Track::query()->firstOrNew([
            'album_id' => $album->id,
            'normalised_title' => $this->normalise($trackName),
        ]);
        $track->fill([
            'album_id' => $album->id,
            'title' => $trackName,
            'normalised_title' => $this->normalise($trackName),
            'lastfm_mbid' => $data['track_mbid'] ?? null,
        ]);
        $track->save();

        return $track;
    }

    private function normalise(string $value): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim($value))) ?? '';
    }
}
