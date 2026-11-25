<?php

namespace App\Services\MusicBrainz;

use App\Models\Artist;
use App\Models\ArtistAlias;
use App\Models\ArtistRelationship;

/**
 * Persists non-catalogue MusicBrainz artist context without changing canonical records.
 */
class MusicBrainzArtistMetadataWriter
{
    /**
     * Store aliases and artist relationships returned by MusicBrainz.
     *
     * @param  Artist  $artist  The existing canonical artist.
     * @param  list<array{name: string, locale: ?string, type: ?string}>  $aliases  Provider aliases.
     * @param  list<array{target_id: string, target_name: string, type: string, attributes: list<string>}>  $relationships  Provider relationships.
     */
    public function write(Artist $artist, array $aliases, array $relationships): void
    {
        foreach ($aliases as $alias) {
            ArtistAlias::query()->updateOrCreate(
                [
                    'artist_id' => $artist->id,
                    'normalised_name' => $this->normalise($alias['name']),
                    'source' => 'musicbrainz',
                ],
                [
                    'name' => $alias['name'],
                    'locale' => $alias['locale'],
                    'alias_type' => $alias['type'],
                ],
            );
        }

        foreach ($relationships as $relationship) {
            $relatedArtist = Artist::query()
                ->where('musicbrainz_mbid', $relationship['target_id'])
                ->first();

            ArtistRelationship::query()->updateOrCreate(
                [
                    'artist_id' => $artist->id,
                    'source' => 'musicbrainz',
                    'related_provider_id' => $relationship['target_id'],
                    'relationship_type' => $relationship['type'],
                ],
                [
                    'related_artist_id' => $relatedArtist?->id,
                    'related_name' => $relationship['target_name'],
                    'metadata' => ['attributes' => $relationship['attributes']],
                ],
            );
        }
    }

    /**
     * Normalise an alias for its provider-scoped unique key.
     */
    private function normalise(string $value): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim($value))) ?? '';
    }
}
