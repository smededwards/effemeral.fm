<?php

namespace App\Models;

use App\Models\Concerns\HasEfmId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Canonical artist record with source identities and accepted genres.
 */
class Artist extends Model
{
    use HasEfmId;

    /** EFMID entity type used in generated identifiers. */
    protected const EFM_TYPE = 'ART';

    protected $fillable = [
        'name', 'normalised_name', 'location', 'location_manual', 'region', 'region_manual', 'country_code', 'country_manual',
        'spotify_id', 'lastfm_mbid', 'musicbrainz_mbid', 'musicbrainz_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'location_manual' => 'boolean',
            'region_manual' => 'boolean',
            'country_manual' => 'boolean',
            'musicbrainz_checked_at' => 'immutable_datetime',
        ];
    }

    /**
     * Limit provider enrichment to artists grounded in Last.fm or Spotify data.
     */
    public function scopeWithSourceEvidence(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $query): void {
                $query
                    ->whereNotNull('artists.spotify_id')
                    ->orWhereNotNull('artists.lastfm_mbid')
                    ->orWhereExists(function ($query): void {
                        $query->selectRaw('1')
                            ->from('scrobbles')
                            ->whereColumn('scrobbles.artist_id', 'artists.id');
                    })
                    ->orWhereHas('albums', function (Builder $query): void {
                        $query->whereNotNull('albums.spotify_id')
                            ->orWhereNotNull('albums.lastfm_mbid')
                            ->orWhereHas('tracks', function (Builder $query): void {
                                $query->whereNotNull('tracks.spotify_id')
                                    ->orWhereNotNull('tracks.lastfm_mbid');
                            });
                    });
            });
    }

    /**
     * Limit Explore results to artists with catalogue data to display.
     */
    public function scopeWithCatalogueData(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query
                ->whereHas('albums')
                ->orWhereHas('genres');
        });
    }

    /**
     * Return albums credited to the artist.
     */
    public function albums(): BelongsToMany
    {
        return $this->belongsToMany(Album::class);
    }

    /**
     * Return MusicBrainz enrichment runs for the artist.
     */
    public function musicBrainzDiscographyImportRuns(): HasMany
    {
        return $this->hasMany(MusicBrainzDiscographyImportRun::class);
    }

    /**
     * Return provider aliases for this artist without changing the canonical name.
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(ArtistAlias::class);
    }

    /**
     * Return provider relationships from this artist to related artists.
     */
    public function relationships(): HasMany
    {
        return $this->hasMany(ArtistRelationship::class);
    }

    /**
     * Return all imported and manual genre links.
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class)->withPivot('source', 'suppressed')->withTimestamps();
    }

    /**
     * Return non-suppressed genre links used by discovery.
     */
    public function acceptedGenres(): BelongsToMany
    {
        return $this->genres()
            ->wherePivot('suppressed', false)
            ->whereIn('genres.id', Genre::filterableIds())
            ->select('genres.*')
            ->distinct();
    }
}
