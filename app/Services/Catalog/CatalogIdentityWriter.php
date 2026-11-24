<?php

namespace App\Services\Catalog;

use App\Models\Album;
use App\Models\Artist;
use App\Models\CatalogIdentity;
use App\Models\Track;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Keeps external provider identities linked to canonical catalogue records.
 */
class CatalogIdentityWriter
{
    /**
     * Link the provider identifiers currently present on an artist.
     */
    public function syncArtist(Artist $artist): void
    {
        $this->sync($artist, 'artist', [
            ['provider' => 'spotify', 'identifier_type' => 'spotify_id', 'identifier' => $artist->spotify_id, 'url_prefix' => 'https://open.spotify.com/artist/'],
            ['provider' => 'lastfm', 'identifier_type' => 'mbid', 'identifier' => $artist->lastfm_mbid],
            ['provider' => 'musicbrainz', 'identifier_type' => 'mbid', 'identifier' => $artist->musicbrainz_mbid, 'url_prefix' => 'https://musicbrainz.org/artist/'],
        ]);
    }

    /**
     * Link the provider identifiers currently present on an album.
     */
    public function syncAlbum(Album $album): void
    {
        $this->sync($album, 'album', [
            ['provider' => 'spotify', 'identifier_type' => 'spotify_id', 'identifier' => $album->spotify_id, 'url' => $album->spotify_url],
            ['provider' => 'lastfm', 'identifier_type' => 'mbid', 'identifier' => $album->lastfm_mbid],
            ['provider' => 'musicbrainz', 'identifier_type' => 'release_group_mbid', 'identifier' => $album->musicbrainz_release_group_mbid, 'url_prefix' => 'https://musicbrainz.org/release-group/'],
        ]);
    }

    /**
     * Link the provider identifiers currently present on a track.
     */
    public function syncTrack(Track $track): void
    {
        $this->sync($track, 'track', [
            ['provider' => 'spotify', 'identifier_type' => 'spotify_id', 'identifier' => $track->spotify_id, 'url_prefix' => 'https://open.spotify.com/track/'],
            ['provider' => 'lastfm', 'identifier_type' => 'mbid', 'identifier' => $track->lastfm_mbid],
        ]);
    }

    /**
     * @param  Model  $entity  The canonical Effemeral record.
     * @param  string  $entityType  The canonical entity type.
     * @param  list<array{provider: string, identifier_type: string, identifier: mixed, url?: string|null, url_prefix?: string}>  $identities  Provider identifiers.
     */
    private function sync(Model $entity, string $entityType, array $identities): void
    {
        foreach ($identities as $identityData) {
            $identifier = $identityData['identifier'];
            if (! is_string($identifier) || trim($identifier) === '') {
                continue;
            }

            $identity = CatalogIdentity::query()->firstOrCreate(
                [
                    'provider' => $identityData['provider'],
                    'entity_type' => $entityType,
                    'identifier_type' => $identityData['identifier_type'],
                    'identifier' => $identifier,
                ],
                [
                    'provider_url' => $identityData['url'] ?? (isset($identityData['url_prefix']) ? $identityData['url_prefix'].$identifier : null),
                    'provider_name' => $entity->getAttribute('name') ?? $entity->getAttribute('title'),
                ],
            );

            // A provider identity can remain linked to more than one record while a match is reviewed.
            DB::table('catalog_identity_links')->updateOrInsert(
                [
                    'catalog_identity_id' => $identity->id,
                    'entity_type' => $entityType,
                    'entity_id' => $entity->getKey(),
                ],
                [
                    'match_status' => 'confirmed',
                    'match_method' => $identityData['identifier_type'],
                    'confidence' => 100,
                    'is_primary' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }
}
