<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Canonical artist record with source identities and accepted genres.
 */
class Artist extends Model
{
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
