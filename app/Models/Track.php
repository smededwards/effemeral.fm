<?php

namespace App\Models;

use App\Models\Concerns\HasEfmId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Canonical track record linked to an album and source identifiers.
 */
class Track extends Model
{
    use HasEfmId;

    /** EFMID entity type used in generated identifiers. */
    protected const EFM_TYPE = 'TRK';

    protected $fillable = [
        'album_id', 'title', 'normalised_title', 'spotify_id', 'lastfm_mbid',
        'spotify_uri', 'disc_number', 'track_number', 'duration_ms',
    ];

    /**
     * Preserve the initial album release association for tracks created after the pivot migration.
     */
    protected static function booted(): void
    {
        static::created(function (Track $track): void {
            $track->releases()->syncWithoutDetaching([
                $track->album_id => [
                    'disc_number' => $track->disc_number,
                    'track_number' => $track->track_number,
                ],
            ]);
        });
    }

    /**
     * Return the album containing the track.
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    /**
     * Return every album release containing this canonical track.
     */
    public function releases(): BelongsToMany
    {
        return $this->belongsToMany(Album::class, 'album_track_releases')
            ->withPivot('disc_number', 'track_number')
            ->withTimestamps();
    }
}
